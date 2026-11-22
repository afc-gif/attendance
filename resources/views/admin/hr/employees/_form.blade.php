@php($employee = $employee ?? null)
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
    <h2 class="font-extrabold text-sm text-slate-800">Personal Information</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <label class="text-xs font-bold text-slate-600">Company *<select name="company" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"><option value="">Select</option>@foreach(['ARTSCI','AFC'] as $company)<option value="{{ $company }}" @selected(old('company', $employee?->company) === $company)> {{ $company }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">First name *<input name="first_name" required value="{{ old('first_name', $employee?->first_name) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Middle name<input name="middle_name" value="{{ old('middle_name', $employee?->middle_name) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Last name *<input name="last_name" required value="{{ old('last_name', $employee?->last_name) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($employee?->date_of_birth)->format('Y-m-d')) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Gender<select name="gender" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"><option value="">Select</option>@foreach(['Female','Male','Other','Prefer not to say'] as $gender)<option value="{{ $gender }}" @selected(old('gender', $employee?->gender) === $gender)>{{ $gender }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Nationality<input name="nationality" value="{{ old('nationality', $employee?->nationality) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Phone<input name="phone" value="{{ old('phone', $employee?->phone) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Email<input type="email" name="email" value="{{ old('email', $employee?->email) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
    </div>
    <label class="block text-xs font-bold text-slate-600">Residential address<textarea name="residential_address" rows="2" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">{{ old('residential_address', $employee?->residential_address) }}</textarea></label>
    <label class="block text-xs font-bold text-slate-600">Religion <span class="font-normal text-slate-400">(restricted HR information)</span><input name="religion" value="{{ old('religion', $employee?->religion) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
</div>

<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
    <h2 class="font-extrabold text-sm text-slate-800">Family and Emergency Information</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <label class="text-xs font-bold text-slate-600">Marital status<input name="marital_status" value="{{ old('marital_status', $employee?->marital_status) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Next of kin<input name="next_of_kin" value="{{ old('next_of_kin', $employee?->next_of_kin) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Relationship<input name="next_of_kin_relationship" value="{{ old('next_of_kin_relationship', $employee?->next_of_kin_relationship) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Next-of-kin phone<input name="next_of_kin_phone" value="{{ old('next_of_kin_phone', $employee?->next_of_kin_phone) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Emergency contact name<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee?->emergency_contact_name) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Emergency phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employee?->emergency_contact_phone) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Emergency relationship<input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $employee?->emergency_contact_relationship) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
    </div>
    <label class="block text-xs font-bold text-slate-600">Next-of-kin address<textarea name="next_of_kin_address" rows="2" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">{{ old('next_of_kin_address', $employee?->next_of_kin_address) }}</textarea></label>
</div>

<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
    <h2 class="font-extrabold text-sm text-slate-800">Employment Information</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <label class="text-xs font-bold text-slate-600">Department<select name="department_id" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $employee?->department_id) == $department->id)>{{ $department->company }} · {{ $department->name }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Position<select name="position_id" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"><option value="">None</option>@foreach($positions as $position)<option value="{{ $position->id }}" @selected(old('position_id', $employee?->position_id) == $position->id)>{{ $position->company }} · {{ $position->title }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Reporting manager<select name="reporting_manager_id" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"><option value="">None</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected(old('reporting_manager_id', $employee?->reporting_manager_id) == $manager->id)>{{ $manager->full_name }} ({{ $manager->employee_id }})</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Optional login account
            <select name="user_id" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">
                <option value="">No account linked</option>
                @if($employee?->user)
                    <option value="{{ $employee->user_id }}" selected>{{ $employee->user->name }}</option>
                @endif
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected(old('user_id', $employee?->user_id) == $user->id)>{{ $user->name }} · {{ $user->email }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-bold text-slate-600">Employment type<select name="employment_type" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">@foreach(['full_time','part_time','contract','intern','temporary'] as $type)<option value="{{ $type }}" @selected(old('employment_type', $employee?->employment_type ?? 'full_time') === $type)>{{ ucfirst(str_replace('_',' ', $type)) }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Employment date<input type="date" name="employment_date" value="{{ old('employment_date', optional($employee?->employment_date)->format('Y-m-d')) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Probation start<input type="date" name="probation_start_date" value="{{ old('probation_start_date', optional($employee?->probation_start_date)->format('Y-m-d')) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Probation end<input type="date" name="probation_end_date" value="{{ old('probation_end_date', optional($employee?->probation_end_date)->format('Y-m-d')) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
        <label class="text-xs font-bold text-slate-600">Employment status<select name="employment_status" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">@foreach(['active','inactive','terminated','resigned'] as $status)<option value="{{ $status }}" @selected(old('employment_status', $employee?->employment_status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label class="text-xs font-bold text-slate-600">Confirmation status<select name="confirmation_status" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg">@foreach(['pending','probation','confirmed'] as $status)<option value="{{ $status }}" @selected(old('confirmation_status', $employee?->confirmation_status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    </div>
</div>

<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
    <h2 class="font-extrabold text-sm text-slate-800">Secure Documents</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-bold text-slate-600">
        @foreach(['cv'=>'CV','certificates'=>'Certificates','identification_document'=>'Identification document','employment_letter'=>'Employment letter','supporting_document'=>'Other supporting document'] as $name => $label)
            <label>{{ $label }}<input type="file" name="{{ $name }}" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 w-full text-xs"></label>
        @endforeach
    </div>
</div>

<div class="flex justify-end gap-3">
    <a href="{{ $employee ? route('admin.hr.employees.show', $employee) : route('admin.hr.employees.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700">Cancel</a>
    <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold">{{ $employee ? 'Save Changes' : 'Create Employee' }}</button>
</div>
