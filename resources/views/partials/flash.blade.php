@if (session('ok'))<div class="alert ok" data-flash="ok">{{ session('ok') }}</div>@endif
@if (session('error'))<div class="alert bad" data-flash="error">{{ session('error') }}</div>@endif
@if ($errors->any())
    <div class="alert bad"><b>Periksa kembali:</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
