
<form method="POST" action="{{ route('login') }}">

    @csrf
    @method('post')

    <label for="username">Username</label>
    <input type="text" name="username" value="{{ old('username') }}" id="username" />

    <label for="password">Password</label>
    <input type="password" name="password" id="password" />

    <input type="submit" value="Login" />

</form>
