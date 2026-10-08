<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $teachers = Teacher::with('room')
            ->withCount('equipment')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('first_name', 'like', "%{$request->search}%")
                ->orWhere('last_name', 'like', "%{$request->search}%")
                ->orWhere('employee_id', 'like', "%{$request->search}%")))
            ->orderBy('last_name')
            ->get()
            ->map(fn ($t) => $this->transform($t));

        return $this->ok($teachers);
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = Teacher::create($this->validated($request));
        ActivityLog::record('created', 'teachers', "Created teacher record for {$teacher->full_name}.");

        return $this->ok($this->transform($teacher->load('room')->loadCount('equipment')), 'Teacher created.', 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $teacher->update($this->validated($request, $teacher));
        ActivityLog::record('updated', 'teachers', "Updated teacher record for {$teacher->full_name}.");

        return $this->ok($this->transform($teacher->load('room')->loadCount('equipment')), 'Teacher updated.');
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        if ($teacher->equipment()->exists()) {
            return $this->fail('Cannot delete a teacher who still has equipment assigned to them.');
        }

        $name = $teacher->full_name;
        $teacher->delete();
        ActivityLog::record('deleted', 'teachers', "Deleted teacher record for {$name}.");

        return $this->ok(null, 'Teacher deleted.');
    }

    private function transform(Teacher $t): array
    {
        return [
            'id' => $t->id,
            'employee_id' => $t->employee_id,
            'first_name' => $t->first_name,
            'last_name' => $t->last_name,
            'full_name' => $t->full_name,
            'department' => $t->department,
            'position' => $t->position,
            'email' => $t->email,
            'contact_number' => $t->contact_number,
            'room_id' => $t->room_id,
            'room_name' => $t->room?->name,
            'is_active' => (bool) $t->is_active,
            'equipment_count' => $t->equipment_count ?? 0,
        ];
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:255', Rule::unique('teachers', 'employee_id')->ignore($teacher?->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
