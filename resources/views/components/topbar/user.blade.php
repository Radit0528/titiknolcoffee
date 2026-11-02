@php($user = auth()->user())
<div class="topbar-user">
    <div class="hidden text-right leading-tight sm:block">
        <p class="text-sm font-semibold">{{ $user->name }}</p>
        <span class="role-chip">{{ $user->role->label() }}</span>
    </div>
    <span class="avatar">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span>
</div>
