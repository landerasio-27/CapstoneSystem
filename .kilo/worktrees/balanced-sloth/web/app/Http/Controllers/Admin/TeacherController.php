<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Custodian Folders — the module behind Organization → Custodians.
 *
 * A folder is not a record of its own: it is one custodian (a `teachers` row)
 * seen together with everything that names them. A PAR, an ICS, a RIS and a
 * piece of equipment each carry a `teacher_id`, so assigning any of them
 * anywhere in the system files it in the folder immediately. This controller
 * only reads that arrangement back, plus the two ways a folder is filled from
 * its own page (a document created for this custodian, an item assigned here).
 */
class TeacherController extends Controller
{
    /** How many folder cards one page of the grid shows. */
    private const PER_PAGE = 12;

    /**
     * How many documents a folder lists before it offers the rest. A custodian
     * can hold dozens of papers, and the folder is read for what is in it — the
     * newest of each type — rather than as the register, which lists them all.
     */
    private const DOCUMENT_PREVIEW = 10;

    /** Document types the folder lists, with the names the printed forms carry. */
    public const DOCUMENT_TYPES = [
        'PAR' => 'Property Acknowledgement Receipt',
        'ICS' => 'Inventory Custodian Slip',
        'RIS' => 'Requisition and Issue Slip',
        'IAR' => 'Inspection and Acceptance Report',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search'));
        $sort = (string) $request->get('sort', 'name_asc');

        $teachers = Teacher::query()
            ->select('teachers.*')
            // "Last updated" is the newest thing that happened in the folder, not
            // just the record: a PAR filed this morning makes the folder current.
            // Selected as columns so the grid can order by them in SQL.
            ->addSelect([
                'latest_document_at' => Document::query()->select('updated_at')
                    ->whereColumn('documents.teacher_id', 'teachers.id')
                    ->whereNull('archive_id')
                    ->orderByDesc('updated_at')
                    ->limit(1),
                'latest_equipment_at' => Equipment::query()->select('updated_at')
                    ->whereColumn('equipment.teacher_id', 'teachers.id')
                    ->orderByDesc('updated_at')
                    ->limit(1),
            ])
            ->with('room')
            // The counts the cards print come straight off the relations, so a
            // document or an item filed elsewhere lands in the figures too.
            ->withCount([
                'equipment',
                'documents' => fn ($query) => $query->whereNull('archive_id'),
                'documents as par_count' => fn ($query) => $query->whereNull('archive_id')->where('type', 'PAR'),
                'documents as ics_count' => fn ($query) => $query->whereNull('archive_id')->where('type', 'ICS'),
                'documents as ris_count' => fn ($query) => $query->whereNull('archive_id')->where('type', 'RIS'),
            ])
            // Every word has to land somewhere on the record, so "Bautista
            // Business" and "Cristina" both work.
            ->when($search !== '', function ($query) use ($search) {
                foreach (preg_split('/\s+/', $search) as $word) {
                    $query->where(function ($sub) use ($word) {
                        $sub->where('first_name', 'like', "%{$word}%")
                            ->orWhere('last_name', 'like', "%{$word}%")
                            ->orWhere('employee_id', 'like', "%{$word}%")
                            ->orWhere('department', 'like', "%{$word}%")
                            ->orWhere('position', 'like', "%{$word}%")
                            ->orWhereHas('room', fn ($room) => $room
                                ->where('name', 'like', "%{$word}%")
                                ->orWhere('code', 'like', "%{$word}%"));
                    });
                }
            })
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('last_name')->orderByDesc('first_name'))
            ->when($sort === 'recent', fn ($query) => $query->orderByRaw('COALESCE(latest_document_at, latest_equipment_at, teachers.updated_at) DESC'))
            ->when($sort === 'equipment', fn ($query) => $query->orderByDesc('equipment_count')->orderBy('last_name'))
            ->when($sort === 'documents', fn ($query) => $query->orderByDesc('documents_count')->orderBy('last_name'))
            ->when(! in_array($sort, ['name_desc', 'recent', 'equipment', 'documents'], true),
                fn ($query) => $query->orderBy('last_name')->orderBy('first_name'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.teachers.index', [
            'teachers' => $teachers,
            'sort' => $sort,
            'rooms' => Room::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $rooms = Room::orderBy('name')->get();

        return view('admin.teachers.create', compact('rooms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $teacher = Teacher::create($data);

        ActivityLog::record('created', 'teachers', "Created custodian folder for {$teacher->full_name}.");

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', "Custodian folder \"{$teacher->full_name}\" created.");
    }

    /**
     * One custodian's folder: their information, the documents and equipment
     * that name them, and what was recorded recently.
     *
     * The document filter and search only narrow the list on this page — the
     * summary cards always count the whole folder, so filtering never hides how
     * much is in it.
     */
    public function show(Request $request, Teacher $teacher): View
    {
        // The record is edited from this page as well as from the edit page, so
        // the sheet that changes it needs the rooms the record can be given.
        $rooms = Room::orderBy('name')->get();

        $documentType = (string) $request->get('documents', '');
        $documentSearch = trim((string) $request->get('doc_q'));
        // The folder is read one part at a time — its papers, or its property —
        // and the panel is in the address, so a tab can be linked to and the
        // page comes back to it after assigning an item.
        $panel = $request->get('panel') === 'equipment' ? 'equipment' : 'documents';

        if (! array_key_exists($documentType, self::DOCUMENT_TYPES)) {
            $documentType = '';
        }

        $showAllDocuments = $request->boolean('doc_all');

        $documentQuery = $teacher->documents()
            // Filed documents live in the archive, as they do in the register.
            ->notArchived()
            ->when($documentType !== '', fn ($query) => $query->where('type', $documentType))
            ->when($documentSearch !== '', fn ($query) => $query->where(function ($sub) use ($documentSearch) {
                $sub->where('document_no', 'like', "%{$documentSearch}%")
                    ->orWhere('remarks', 'like', "%{$documentSearch}%");
            }));

        // How many documents the list is choosing from, so the section can say
        // "showing 10 of 32" and offer the rest without the reader counting.
        $documentsMatching = (clone $documentQuery)->count();

        $documents = $documentQuery
            // Grouped by type (PAR, then ICS, then RIS, then anything else) and
            // newest first inside each group.
            ->orderByRaw("CASE type WHEN 'PAR' THEN 1 WHEN 'ICS' THEN 2 WHEN 'RIS' THEN 3 ELSE 4 END")
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->when(! $showAllDocuments, fn ($query) => $query->limit(self::DOCUMENT_PREVIEW))
            ->get();

        $equipment = $teacher->equipment()
            // The transactions are loaded with the item so the assigned date and
            // the folder's history are read without a query per row.
            ->with(['transactions' => fn ($query) => $query->orderByDesc('transaction_date')->orderByDesc('id')])
            ->orderBy('name')
            ->get();

        // Read off the relations, never stored: the numbers are the folder.
        $summary = [
            'documents' => $teacher->documents()->notArchived()->count(),
            'equipment' => $equipment->count(),
            'PAR' => $teacher->documents()->notArchived()->where('type', 'PAR')->count(),
            'ICS' => $teacher->documents()->notArchived()->where('type', 'ICS')->count(),
            'RIS' => $teacher->documents()->notArchived()->where('type', 'RIS')->count(),
        ];

        return view('admin.teachers.show', [
            'teacher' => $teacher,
            'rooms' => $rooms,
            'panel' => $panel,
            'documents' => $documents,
            'documentsMatching' => $documentsMatching,
            'showAllDocuments' => $showAllDocuments,
            'documentPreview' => self::DOCUMENT_PREVIEW,
            'equipment' => $equipment,
            'summary' => $summary,
            'documentType' => $documentType,
            'documentSearch' => $documentSearch,
            // What the "Assign Equipment" sheet offers: anything not already in
            // this folder, so an item can be transferred here from someone else.
            'assignable' => Equipment::with('teacher')
                ->where(fn ($query) => $query->whereNull('teacher_id')->orWhere('teacher_id', '!=', $teacher->id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Teacher $teacher): View
    {
        $rooms = Room::orderBy('name')->get();

        return view('admin.teachers.edit', compact('teacher', 'rooms'));
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $this->validated($request, $teacher);

        $teacher->update($data);

        ActivityLog::record('updated', 'teachers', "Updated custodian folder for {$teacher->full_name}.");

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', "Custodian folder \"{$teacher->full_name}\" updated successfully.");
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        if ($teacher->equipment()->exists()) {
            return back()->with('error', 'Cannot delete a custodian who still has equipment assigned to them.');
        }

        $name = $teacher->full_name;
        $teacher->delete();

        ActivityLog::record('deleted', 'teachers', "Deleted custodian record for {$name}.");

        return redirect()->route('admin.teachers.index')->with('success', "Custodian \"{$name}\" deleted successfully.");
    }

    /**
     * "Create Folder" from the folder grid.
     *
     * A folder needs nothing of its own inserted — a custodian's folder is the
     * documents and equipment that name them — so this only creates the person
     * the folder belongs to, with the same fields the custodian form takes, and
     * opens the folder that is theirs from that moment. Someone already in the
     * directory needs no sheet: their folder is on the grid to be opened.
     */
    public function storeFolder(Request $request): RedirectResponse
    {
        $teacher = Teacher::create($this->validated($request));

        ActivityLog::record('created', 'teachers', "Created custodian folder for {$teacher->full_name}.");

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', "Custodian folder \"{$teacher->full_name}\" created.");
    }

    /**
     * Assign equipment to a custodian from their folder.
     *
     * This sets the same `teacher_id` the equipment form sets: the item is not
     * copied into the folder, it is the folder. An item that was available
     * becomes issued, the way issuing a PAR/ICS hands it over; anything already
     * carrying a state (damaged, missing) keeps it.
     */
    public function assignEquipment(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate(
            ['equipment_id' => ['required', 'exists:equipment,id']],
            ['equipment_id.required' => 'Choose the equipment to assign.'],
            ['equipment_id' => 'equipment'],
        );

        $equipment = Equipment::with('teacher')->findOrFail($data['equipment_id']);
        $previous = $equipment->teacher;

        $equipment->update([
            'teacher_id' => $teacher->id,
            'status' => $equipment->status === 'available' ? 'issued' : $equipment->status,
        ]);

        if (! $previous || $previous->id !== $teacher->id) {
            ActivityLog::record(
                'assigned',
                'equipment',
                "Assigned equipment {$equipment->name} to {$teacher->full_name}"
                    .($previous ? ", transferred from {$previous->full_name}." : '.')
            );
        }

        $message = $previous && $previous->id !== $teacher->id
            ? "{$equipment->name} transferred to {$teacher->full_name} from {$previous->full_name}."
            : "{$equipment->name} assigned to {$teacher->full_name}.";

        return back()->with('success', $message);
    }

    /**
     * The custodian's own fields. The employee ID is not among them: it is the
     * number the system keeps for the record (Teacher::nextEmployeeId), assigned
     * when the record is created and untouched by an edit, so the office never
     * has to invent one.
     */
    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        if (! $teacher) {
            $data['employee_id'] = Teacher::nextEmployeeId();
        }

        return $data;
    }
}
