<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends AdminApiController
{
    /** Lightweight list used to populate the Users form role dropdown. */
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('display_name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'display_name' => $r->display_name,
            ]);

        return $this->ok($roles);
    }
}
