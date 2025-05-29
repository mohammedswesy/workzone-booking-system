@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Edit User</h1>
    <form action="{{ route('users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ $user->email }}" required>
        </div>

       <div class="mb-3">
    <label for="role" class="form-label">Role</label>
    <select name="role" id="role" class="form-control" required>
        <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>User</option>
        <option value="owner" {{ $user->role === 'owner' ? 'selected' : '' }}>Owner</option>
        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
    </select>
</div>

</div>

        <button type="submit" class="btn btn-success">Update</button>
    </form>
</div>
@endsection
