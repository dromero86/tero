@extends('main')

@section('title', 'Test View - {{ app_name }}')

@section('content')
<div class="test-content">
    <h2>¡Hola desde Tero Framework!</h2>
    <p>Esta es una vista de prueba.</p>
    <p>App Name: {{ app_name }}</p>
    <p>App Version: {{ app_version }}</p>
    <p>Current Time: {{ current_time }}</p>
    
    @if(isset($user))
        <p>Usuario: {{ user.name }}</p>
    @else
        <p>No hay usuario logueado</p>
    @endif
    
    <ul>
        @foreach($items as $item)
            <li>{{ item.name }} - {{ item.price }}</li>
        @endforeach
    </ul>
</div>
@endsection