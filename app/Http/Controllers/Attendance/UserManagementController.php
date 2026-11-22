<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    protected function authorizeAdmin(): void
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized. Restricted to administrators.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $search = trim((string) $request->input('search', ''));
        $roleFilter = $request->input('role');
        $statusFilter = $request->input('status');

        $query = User::query()->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $users = $query->paginate(20)->appends($request->query());

        $totalUsers = User::count();
        $pendingCount = User::where('status', 'pending')->count();
        $approvedCount = User::where('status', 'approved')->count();
        $adminCount = User::where('role', 'admin')->count();
        $afcCount = User::where('role', 'afc_staff')->count();
        $artsciCount = User::where('role', 'artsci_staff')->count();

        return view('admin.users.index', compact(
            'users',
            'search',
            'roleFilter',
            'statusFilter',
            'totalUsers',
            'pendingCount',
            'approvedCount',
            'adminCount',
            'afcCount',
            'artsciCount'
        ));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'status' => ['required', 'string', 'in:pending,approved,rejected,suspended'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.users.index')->with('success', "User account for {$user->name} created successfully as ".$user->role_label.'.');
    }

    public function show(User $user)
    {
        $this->authorizeAdmin();

        $records = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('attendance_date', 'desc')
            ->limit(10)
            ->get();

        $credentials = $user->attendanceCredentials()->orderBy('registered_at', 'desc')->get();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $allRecords = AttendanceRecord::where('user_id', $user->id)->get();

        $stats = [
            'total_present' => $allRecords->whereNotNull('check_in_at')->count(),
            'late_week' => $allRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date?->between($startOfWeek, $endOfWeek))->count(),
            'late_month' => $allRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date?->month === $currentMonth && $r->attendance_date?->year === $currentYear)->count(),
            'late_year' => $allRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date?->year === $currentYear)->count(),
            'late_all_time' => $allRecords->where('status', 'late')->count(),
        ];

        $stats['punctuality_rate'] = $stats['total_present'] > 0
            ? round((($stats['total_present'] - $stats['late_all_time']) / $stats['total_present']) * 100, 1)
            : 100.0;

        return view('admin.users.show', compact('user', 'records', 'credentials', 'stats'));
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin();
        $this->authorizeHrRoleManagement($user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();
        $this->authorizeHrRoleManagement($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'status' => ['required', 'string', 'in:pending,approved,rejected,suspended'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('admin.users.show', $user)->with('success', "User profile for {$user->name} updated successfully. Role set to: ".$user->role_label.'.');
    }

    private function assignableRoles(): array
    {
        $roles = ['admin', 'afc_staff', 'artsci_staff'];
        $actor = Auth::user();

        if ($actor instanceof User && $actor->isSuperAdmin()) {
            $roles[] = 'hr';
        }

        return $roles;
    }

    private function authorizeHrRoleManagement(User $user): void
    {
        if ($user->isHr()) {
            $actor = Auth::user();

            if (! ($actor instanceof User) || ! $actor->isSuperAdmin()) {
                abort(403, 'Only Super Admin can manage HR accounts.');
            }
        }
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->authorizeAdmin();
        $this->authorizeHrRoleManagement($user);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected,suspended'],
        ]);

        $user->update(['status' => $validated['status']]);

        $statusLabel = ucfirst($validated['status']);

        return back()->with('success', "{$user->name}'s account has been set to {$statusLabel}.");
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin();
        $this->authorizeHrRoleManagement($user);

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->id === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "User account for {$name} has been permanently deleted.");
    }
}
