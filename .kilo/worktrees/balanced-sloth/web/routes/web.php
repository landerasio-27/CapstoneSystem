<?php

use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SupplyController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    /*
     * Forgot password, in three steps: the account's email is written down, a
     * six-digit code is mailed to that address, and only a code accepted back
     * opens the form the new password is written on (see PasswordResetController).
     *
     * Posting either the email or the code is throttled: both ask the office's
     * own mailbox to mail something, and both stand for an account to be worked
     * over — so a page that answers is limited to a handful of tries a minute,
     * and a wrong code wears its own code out rather than the door.
     */
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/verify-code', [PasswordResetController::class, 'code'])->name('password.code');
    Route::post('/verify-code', [PasswordResetController::class, 'verify'])->middleware('throttle:10,1')->name('password.code.verify');
    Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

/*
 * Shared panel: both the administrator and the supply staff live here. The
 * sidebar hides what the signed-in role cannot open, and the back office —
 * users, the activity log and the archive — is guarded by its own role
 * middleware below, so the hiding is enforced rather than cosmetic.
 *
 * Everything the supply office works with is open to both roles: the
 * inventory, the rooms and custodians it points at, the document register,
 * the cards ledger and the reports. Each of those is declared here once,
 * with its write routes — a second definition of the same URI in the
 * administrator-only group below would shadow these and answer the supply
 * staff account with a 403 it should never have seen.
 */
Route::middleware(['auth', 'role:admin,inspection_staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/account-settings', [AccountSettingsController::class, 'index'])->name('account-settings.index');
    Route::post('/account-settings/users', [AccountSettingsController::class, 'storeUser'])->name('account-settings.users.store');
    Route::post('/account-settings/archive', [AccountSettingsController::class, 'storeArchive'])->name('account-settings.archive.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Rooms, teachers, equipment and supplies are the records the supply
    // office keeps: the staff who work the register add to them, correct them
    // and retire them, and the administrator does the same.
    Route::resource('rooms', RoomController::class);
    Route::resource('teachers', TeacherController::class);
    /*
     * The Custodian Folders module fills a folder from the folder's own page:
     * "Create Folder" (which opens an existing custodian's folder, or adds the
     * person first when the office is filing someone new) and "Assign
     * Equipment" (which sets the item's custodian). Both are declared here — in
     * the shared group — because a route of the same URI registered a second
     * time with a narrower role would shadow these for the supply staff.
     */
    Route::post('/teachers/folder', [TeacherController::class, 'storeFolder'])->name('teachers.folder.store');
    Route::post('/teachers/{teacher}/equipment', [TeacherController::class, 'assignEquipment'])->name('teachers.equipment.store');
    // Equipment is never deleted from the UI (records are kept for audit, and the
    // quantity/cost history hangs off them).
    Route::resource('equipment', EquipmentController::class)->except('destroy');
    // Quantity and cost only ever change through a recorded receipt.
    Route::post('/equipment/{equipment}/quantity', [EquipmentController::class, 'addQuantity'])->name('equipment.quantity');
    // Supplies are never deleted from the UI (records are kept for audit);
    // stock is only ever increased through the Add Stock transaction.
    Route::resource('supplies', SupplyController::class)->except('destroy');
    Route::post('/supplies/{supply}/stock', [SupplyController::class, 'addStock'])->name('supplies.stock');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    /*
     * The table reports: the figures behind the register. They are read-only
     * views of the records both roles already work, and they are declared
     * before the {type} routes below so a report named "inventory-summary"
     * never has to be told apart from a document type.
     */
    Route::get('/reports/inventory-summary', [ReportController::class, 'inventorySummary'])->name('reports.inventory-summary');
    Route::get('/reports/supply-issuance', [ReportController::class, 'supplyIssuance'])->name('reports.supply-issuance');
    Route::get('/reports/document-register', [ReportController::class, 'documentRegister'])->name('reports.document-register');

    /*
     * The document register — the PAR / ICS / RIS (and IAR) papers itself, not
     * a report about them. The office's own staff prepare, approve and issue
     * those papers, so the whole register is shared: they can raise a document,
     * take it through approval and issuance, and print or export it. The
     * archive the finalized papers are filed into stays with the administrator.
     */
    Route::resource('documents', DocumentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/documents/{document}/approve', [DocumentController::class, 'approve'])->name('documents.approve');
    Route::post('/documents/{document}/issue', [DocumentController::class, 'issue'])->name('documents.issue');
    Route::get('/documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
    Route::get('/documents/{document}/bundle-pdf', [DocumentController::class, 'bundlePdf'])->name('documents.bundle-pdf');

    /*
     * Cards — the digital Stock Card (SC) and Property Card (PC) used by the
     * supply/property office. A card documents one existing supply or
     * equipment item; its lines are projected from the stock ledger and from
     * PAR/ICS/IAR document lines, never re-typed.
     *
     * The register the finalized papers post to is the office's own stock and
     * property ledger, so the supply staff work it alongside the
     * administrator. The archive the filed cards go to stays with the
     * administrator.
     */
    Route::get('/cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('/cards/stock', [CardController::class, 'stock'])->name('cards.stock');
    Route::get('/cards/property', [CardController::class, 'property'])->name('cards.property');
    Route::get('/cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/cards', [CardController::class, 'store'])->name('cards.store');
    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show')->whereNumber('card');
    Route::get('/cards/{card}/edit', [CardController::class, 'edit'])->name('cards.edit')->whereNumber('card');
    Route::put('/cards/{card}', [CardController::class, 'update'])->name('cards.update')->whereNumber('card');
    Route::post('/cards/{card}/transactions', [CardController::class, 'storeTransaction'])->name('cards.transactions.store')->whereNumber('card');
    Route::post('/cards/{card}/sync', [CardController::class, 'sync'])->name('cards.sync')->whereNumber('card');
    Route::get('/cards/{card}/print', [CardController::class, 'print'])->name('cards.print')->whereNumber('card');
    Route::get('/cards/{card}/pdf', [CardController::class, 'pdf'])->name('cards.pdf')->whereNumber('card');

    /*
     * The official PAR / ICS / RIS forms: PAR and ICS are read off the
     * equipment records the administrator selects, RIS off the RIS documents
     * filed for the chosen month. Each type keeps its own template — the
     * three are different papers and are never merged into one layout.
     *
     * `print` and `pdf` take the very same filters as the generator (they are
     * plain GET links), so the paper, the preview and the PDF cannot drift
     * apart, and neither route writes anything: a report is a read-only view
     * of records that already exist.
     *
     * These are the papers the register above holds, so they are shared with
     * the staff who work it: whoever may raise a document may print it.
     */
    Route::get('/reports/par-ics-ris', [ReportController::class, 'documents'])->name('reports.documents.index');

    /*
     * The Monthly Report prints the same official RIS grid, but in two modes
     * that the {type} routes below cannot carry: one sheet per end-user read
     * off the month's RIS documents, or a blank form to fill in by hand. It
     * therefore keeps routes of its own.
     */
    Route::get('/reports/monthly', [ReportController::class, 'monthlyDocument'])->name('reports.monthly.index');
    Route::get('/reports/monthly/print', [ReportController::class, 'printMonthlyDocument'])->name('reports.monthly.print');
    Route::get('/reports/monthly/pdf', [ReportController::class, 'pdfMonthlyDocument'])->name('reports.monthly.pdf');

    Route::get('/reports/{type}', [ReportController::class, 'document'])
        ->whereIn('type', ['par', 'ics', 'ris'])->name('reports.document.index');
    Route::get('/reports/{type}/print', [ReportController::class, 'printDocument'])
        ->whereIn('type', ['par', 'ics', 'ris'])->name('reports.document.print');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdfDocument'])
        ->whereIn('type', ['par', 'ics', 'ris'])->name('reports.document.pdf');
});

/*
 * Administrator-only modules, and only these three: user management, the
 * activity log and the archive. They are the back office — the accounts the
 * panel hands out, the record of what every account did, and the years
 * finalized papers are filed under — so they stay with the administrator.
 */
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // The audit trail is admin-only for the same reason user management is: it
    // names what every account did, so it is not shown to the accounts it is
    // watching. The settings page hides the section and this guard enforces it.
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::resource('users', UserController::class)->except('show');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    /*
     * Archive — old PAR / ICS / RIS documents and Stock / Property Cards are
     * filed under a year instead of being deleted. Adding a year, filing and
     * restoring are all here; the working lists simply leave archived records
     * out.
     */
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::post('/archive', [ArchiveController::class, 'store'])->name('archive.store');
    Route::post('/archive/documents', [ArchiveController::class, 'archiveDocuments'])->name('archive.documents.store');
    Route::post('/archive/cards', [ArchiveController::class, 'archiveCards'])->name('archive.cards.store');
    Route::patch('/archive/documents/{document}/restore', [ArchiveController::class, 'restoreDocument'])->name('archive.documents.restore');
    Route::patch('/archive/cards/{card}/restore', [ArchiveController::class, 'restoreCard'])->name('archive.cards.restore')->whereNumber('card');
    // No delete: a year is permanent once added, so no filed record can lose
    // the year it belongs to.
    Route::get('/archive/{archive}', [ArchiveController::class, 'show'])->name('archive.show')->whereNumber('archive');
});
