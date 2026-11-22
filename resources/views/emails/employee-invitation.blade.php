<p>Hello {{ $employee->first_name }},</p>

<p>{{ $employee->company }} Attendance has created a staff account for you.</p>

<p>To confirm your account and choose a password, use this invitation link:</p>

<p><a href="{{ $activationUrl }}">Confirm and activate your account</a></p>

<p>This one-time link expires in {{ $expiresInDays }} days. After activation, sign in and register your attendance device.</p>

<p>If you were not expecting this invitation, contact your HR administrator.</p>
