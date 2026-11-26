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

            @if($module === 'employees' && ! $row && auth()->user()->hasRole(['hr', 'super_admin']))
                <div class="field">
                    <label for="account_password">Kata Sandi Akun Karyawan</label>
                    <input id="account_password" type="password" name="account_password" minlength="12"
                           autocomplete="new-password" placeholder="Kosongkan untuk dibuat otomatis">
                    <small>Opsional, minimal 12 karakter. Jika diisi, kata sandi awal akun memakai nilai ini; jika kosong, sistem membuat acak dan menampilkannya sekali.</small>
                    @error('account_password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            @endif
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

        @if($module === 'activities')
            <p class="form-hint">
                Progres dan status dijaga tetap konsisten: <strong>0%</strong> berarti <strong>Belum Dimulai</strong>,
                <strong>1–99%</strong> berarti <strong>Proses</strong> atau <strong>Tertunda</strong>, dan
                <strong>100%</strong> otomatis menjadi <strong>Selesai</strong>. Memilih status Selesai otomatis
                mengisi progres 100%, memilih Belum Dimulai mengembalikan progres ke 0%.
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

    @if($module === 'activities')
        <script>
            (function () {
                const progress = document.getElementById('progress');
                const status = document.getElementById('status');
                if (!progress || !status) return;

                progress.addEventListener('input', function () {
                    const value = Number(this.value);
                    if (value >= 100) status.value = 'Selesai';
                    else if (value === 0) status.value = 'Belum Dimulai';
                    else if (status.value === 'Selesai' || status.value === 'Belum Dimulai') status.value = 'Proses';
                });

                status.addEventListener('change', function () {
                    if (this.value === 'Selesai') progress.value = 100;
                    if (this.value === 'Belum Dimulai') progress.value = 0;
                });
            })();
        </script>
    @endif
@endsection
