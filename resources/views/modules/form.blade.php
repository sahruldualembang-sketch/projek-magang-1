@extends('layouts.app')
@section('title', ($row ? 'Ubah ' : 'Tambah ') . $d['singular'])
@section('heading', $d['title'])

@section('content')
    @php($staffActivity = auth()->user()->role === 'staff' && $module === 'activities')
    <div class="page-intro">
        <div>
            <h1>{{ $row ? 'Ubah' : 'Tambah' }} {{ $d['singular'] }}</h1>
            <p>
                @if($staffActivity)
                    Perbarui progres dan status aktivitas Anda.
                @elseif($row)
                    Perbarui informasi yang diperlukan.
                @else
                    Masukkan informasi dengan lengkap dan benar.
                @endif
            </p>
        </div>
        <a class="btn secondary" href="{{ route('modules.index', $module) }}">← Kembali ke daftar</a>
    </div>

    <form class="panel form-panel" method="POST" enctype="multipart/form-data"
          action="{{ $row ? route('modules.update', [$module, $row->id]) : route('modules.store', $module) }}">
        @csrf
        @if($row) @method('PUT') @endif

        <div class="panel-head form-panel-head">
            <div>
                <h2>Informasi {{ $d['singular'] }}</h2>
                <p>Kolom bertanda <em class="required-mark">*</em> wajib diisi.</p>
            </div>
        </div>

        @if($staffActivity)
            <p class="form-hint">
                <strong>{{ $row->title }}</strong><br>
                Tanggal aktivitas: {{ \Carbon\Carbon::parse($row->activity_date)->format('d/m/Y') }}<br>
                Hanya <strong>progres</strong> dan <strong>status</strong> yang dapat Anda ubah.
            </p>
        @endif

        <div class="form-grid">
            @foreach($d['fields'] as $f)
                @if(!($f['calculated'] ?? false) && !(isset($d['workflow']) && in_array($f['name'], ['status', 'paid_at'], true)) && (! $staffActivity || in_array($f['name'], ['progress', 'status'], true)))
                    <div class="field {{ $f['type'] === 'textarea' ? 'wide' : '' }}">
                        <label for="{{ $f['name'] }}">
                            {{ $f['label'] }}
                            @if(str_contains($f['rules'], 'required')) <em>*</em> @endif
                        </label>

                        @php($value = old($f['name'], $row->{$f['name']} ?? ($defaults[$f['name']] ?? '')))

                        @if($f['type'] === 'file')
                            <input id="{{ $f['name'] }}" type="file" name="{{ $f['name'] }}" accept=".pdf,.jpg,.jpeg,.png">
                            @if($row && $row->file_path)
                                <small>File tersimpan: <a class="table-link" href="{{ route('modules.file', [$module, $row->id]) }}">Lihat file saat ini</a></small>
                            @endif
                        @elseif($f['type'] === 'textarea')
                            <textarea id="{{ $f['name'] }}" name="{{ $f['name'] }}" rows="4" @if(str_contains($f['rules'], 'required')) required @endif>{{ $value }}</textarea>
                        @elseif($f['type'] === 'select')
                            <select id="{{ $f['name'] }}" name="{{ $f['name'] }}" @if(str_contains($f['rules'], 'required')) required @endif>
                                <option value="">Pilih {{ strtolower($f['label']) }}</option>
                                @foreach($f['options'] as $opt)
                                    <option value="{{ $opt }}" @selected($value === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif($f['type'] === 'relation')
                            <select id="{{ $f['name'] }}" name="{{ $f['name'] }}" @if(str_contains($f['rules'], 'required')) required @endif>
                                <option value="">Pilih {{ strtolower($f['label']) }}</option>
                                @foreach($relations[$f['name']] ?? [] as $id => $label)
                                    <option value="{{ $id }}" @selected((string) $value === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $f['name'] }}"
                                   type="{{ $f['type'] === 'number' ? 'number' : $f['type'] }}"
                                   name="{{ $f['name'] }}" value="{{ $value }}"
                                   @if($f['type'] === 'number') step="0.01" min="0" @endif
                                   @if(str_contains($f['rules'], 'required')) required @endif>
                        @endif

                        @error($f['name'])
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>
                @endif
            @endforeach
        </div>

        @if(in_array($module, ['orders', 'invoices', 'payrolls']))
            <p class="form-hint">
                @if($module === 'orders')
                    Total pesanan dihitung otomatis dari jumlah dikalikan harga satuan.
                @elseif($module === 'invoices')
                    Total invoice dihitung otomatis dari subtotal ditambah pajak.
                @else
                    Gaji bersih dihitung otomatis dari gaji pokok ditambah tunjangan dikurangi potongan.
                @endif
            </p>
        @endif

        @if(isset($d['workflow']))
            <p class="form-hint workflow-hint">
                <strong>Status tidak diisi manual.</strong>
                @if($module === 'payrolls')
                    Data baru disimpan sebagai Draft oleh HR. Finance akan menyetujui lalu menandai pembayaran melalui daftar data.
                @else
                    Data baru disimpan sebagai Draft oleh Operations. Ajukan dari daftar data, lalu Finance akan menyetujui dan menandai pembayaran.
                @endif
                Data yang sudah diajukan atau disetujui tidak dapat diubah.
            </p>
        @endif

        <div class="form-actions">
            <a class="btn secondary" href="{{ route('modules.index', $module) }}">Batal</a>
            <button class="btn primary" type="submit">{{ $row ? 'Simpan Perubahan' : 'Simpan Data' }}</button>
        </div>
    </form>
@endsection
