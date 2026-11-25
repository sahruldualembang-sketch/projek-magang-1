@extends('layouts.app')
@section('title', $d['title'])
@section('heading', $d['title'])

@section('content')
    @php $workflow = $d['workflow'] ?? null; @endphp
    <div class="page-intro">
        <div>
            <h1>{{ $d['title'] }}</h1>
            <p>
                @if($module === 'activities' && auth()->user()->role === 'staff')
                    Perbarui progres aktivitas yang tercatat atas nama Anda.
                @else
                    Kelola dan pantau {{ strtolower($d['singular']) }} perusahaan dalam satu halaman.
                @endif
            </p>
        </div>
        @if(auth()->user()->role !== 'staff' && (! $workflow || auth()->user()->hasRole($workflow['maker_roles'])))
            <a class="btn primary" href="{{ route('modules.create', $module) }}">＋ Tambah {{ $d['singular'] }}</a>
        @endif
    </div>

    @if($workflow)
        <p class="form-hint workflow-hint">
            <strong>Alur maker-checker:</strong>
            {{ $module === 'payrolls' ? 'HR input Draft' : 'Operations input Draft' }}
            @if($workflow['pending_status'])
                → diajukan ({{ $workflow['pending_status'] }})
            @endif
            → Finance setujui ({{ $workflow['approved_status'] }})
            → Finance tandai {{ $workflow['paid_status'] }}.
            Data yang sudah diajukan/disetujui terkunci dari perubahan.
        </p>
    @endif

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Daftar {{ $d['title'] }}</h2>
                <p>{{ $rows->total() }} data ditemukan</p>
            </div>
        </div>

        <form class="toolbar module-filter-form"
              method="GET"
              action="{{ route('modules.index', $module) }}">
            <label class="search-field">
                <span>Cari data</span>
                <input type="search"
                       name="q"
                       value="{{ $search }}"
                       placeholder="Masukkan kata kunci...">
                @error('q')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>

            @foreach($filterFields as $key => $filter)
                <label class="search-field">
                    <span>{{ $filter['label'] }}</span>
                    @if($filter['type'] === 'select')
                        <select name="{{ $key }}">
                            <option value="">{{ $filter['empty_label'] ?? 'Semua' }}</option>
                            @foreach($filter['options'] as $option)
                                <option value="{{ $option }}"
                                    @selected(($filters[$key] ?? '') === (string) $option)>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $filter['type'] === 'relation_text' ? 'search' : $filter['type'] }}"
                               name="{{ $key }}"
                               value="{{ $filters[$key] ?? '' }}"
                               placeholder="{{ $filter['type'] === 'relation_text' ? 'Cari nama...' : '' }}">
                    @endif
                    @error($key)
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </label>
            @endforeach

            <div class="filter-actions">
                <button class="btn secondary" type="submit">Terapkan Filter</button>
                <a class="btn ghost" href="{{ route('modules.index', $module) }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach($d['fields'] as $field)
                            <th>{{ $field['label'] }}</th>
                        @endforeach
                        <th class="actions-col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach($d['fields'] as $field)
                                @php $value = $row->{$field['name']} ?? ''; @endphp
                                <td>
                                    @if(($field['type'] === 'number' && str_contains($field['name'], 'salary')) || in_array($field['name'], ['total_amount', 'unit_price', 'amount', 'contract_value', 'acquisition_cost', 'basic_salary', 'allowance', 'deduction', 'net_salary', 'subtotal', 'tax_amount']))
                                        <span class="strong">Rp {{ number_format((float) $value, 0, ',', '.') }}</span>
                                    @elseif($field['type'] === 'date' && $value)
                                        {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                                    @elseif($field['type'] === 'file')
                                        @if($row->file_path)
                                            <a class="table-link" href="{{ route('modules.file', [$module, $row->id]) }}">Unduh file ↗</a>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    @elseif($field['type'] === 'relation')
                                        {{ $field['relation'] === 'employees'
                                            ? (\Illuminate\Support\Facades\DB::table('employees')->where('id', $value)->value('name') ?? '—')
                                            : ($field['relation'] === 'clients'
                                                ? (\Illuminate\Support\Facades\DB::table('clients')->where('id', $value)->value('company_name') ?? '—')
                                                : (\Illuminate\Support\Facades\DB::table('orders')->where('id', $value)->value('order_number') ?? '—')) }}
                                    @elseif($field['name'] === 'progress')
                                        <span class="progress-cell">
                                            <span class="progress-track">
                                                <span class="progress-fill" style="width: {{ max(0, min(100, (int) $value)) }}%"></span>
                                            </span>
                                            <span class="progress-label">{{ (int) $value }}%</span>
                                        </span>
                                    @elseif($field['name'] === 'status' || $field['name'] === 'employment_status' || $field['name'] === 'asset_condition')
                                        <span class="status {{ \Illuminate\Support\Str::slug((string) $value) }}">{{ $value ?: '—' }}</span>
                                    @else
                                        {{ \Illuminate\Support\Str::limit((string) $value, 65) ?: '—' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @php
                                    $status = $row->status ?? null;
                                    $isMaker = $workflow && auth()->user()->hasRole($workflow['maker_roles']);
                                    $isChecker = $workflow && auth()->user()->hasRole($workflow['checker_roles']);
                                    $canEdit = auth()->user()->role !== 'staff'
                                        && (! $workflow || ($isMaker && in_array($status, $workflow['editable_statuses'], true)));
                                    $approvalFrom = $workflow
                                        ? ($workflow['pending_status'] ? [$workflow['pending_status']] : [$workflow['draft_status']])
                                        : [];
                                    $returnable = $workflow
                                        ? array_values(array_filter([$workflow['pending_status'], $workflow['approved_status']]))
                                        : [];
                                    $canSubmit = $workflow && $isMaker && $workflow['pending_status'] && $status === $workflow['draft_status'];
                                    $canEditOwnActivity = auth()->user()->role === 'staff' && $module === 'activities';
                                @endphp
                                <div class="row-actions">
                                    @if($canEdit)
                                        <a href="{{ route('modules.edit', [$module, $row->id]) }}">Ubah</a>
                                    @endif

                                    @if($canEditOwnActivity)
                                        <a href="{{ route('activities.progress.edit', $row->id) }}">Ubah Progres</a>
                                    @endif

                                    @if($canSubmit)
                                        <form method="POST" action="{{ route('modules.submit', [$module, $row->id]) }}">
                                            @csrf
                                            <button class="link-primary" type="submit">Ajukan</button>
                                        </form>
                                    @endif

                                    @if($isChecker && in_array($status, $approvalFrom, true))
                                        <form method="POST" action="{{ route('modules.approve', [$module, $row->id]) }}">
                                            @csrf
                                            <button class="link-success" type="submit">Setujui</button>
                                        </form>
                                    @endif

                                    @if($isChecker && $status === $workflow['approved_status'])
                                        <form method="POST" action="{{ route('modules.pay', [$module, $row->id]) }}">
                                            @csrf
                                            <button class="link-success" type="submit">Tandai Dibayar</button>
                                        </form>
                                    @endif

                                    @if($isChecker && in_array($status, $returnable, true))
                                        <div x-data="{ openReturn: false, returnReason: '' }">
                                            <button class="link-danger" type="button" @click="openReturn = true">Kembalikan</button>
                                            <div class="modal-backdrop" x-show="openReturn" x-cloak
                                                 @click.self="openReturn = false" style="display: none">
                                                <div class="modal-panel" role="dialog" aria-modal="true"
                                                     aria-labelledby="return-title-{{ $row->id }}">
                                                    <h3 id="return-title-{{ $row->id }}">Kembalikan ke Draft</h3>
                                                    <p>Data akan dikembalikan ke Draft agar dapat diperbaiki oleh maker. Alasan wajib diisi dan akan tercatat di riwayat audit.</p>
                                                    <form method="POST" action="{{ route('modules.return', [$module, $row->id]) }}">
                                                        @csrf
                                                        <label for="return-reason-{{ $row->id }}">Alasan pengembalian</label>
                                                        <textarea id="return-reason-{{ $row->id }}" name="reason"
                                                                  x-model="returnReason" rows="4" maxlength="1000"
                                                                  placeholder="Contoh: nominal gaji tidak sesuai kontrak" required></textarea>
                                                        <small class="modal-count"><span x-text="returnReason.length"></span>/1000 karakter</small>
                                                        <div class="modal-actions">
                                                            <button class="btn secondary" type="button" @click="openReturn = false">Batal</button>
                                                            <button class="btn danger" type="submit" :disabled="!returnReason.trim()">Kembalikan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if(! $workflow && auth()->user()->role !== 'staff' && ! in_array($module, ['payrolls', 'invoices', 'expenses']))
                                        <form method="POST" action="{{ route('modules.destroy', [$module, $row->id]) }}"
                                              onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="link-danger" type="submit">Hapus</button>
                                        </form>
                                    @endif

                                    @if(auth()->user()->role === 'staff' && ! $canEditOwnActivity)
                                        <span class="muted">Hanya lihat</span>
                                    @elseif($workflow && ! $canEdit && ! $isMaker && ! $isChecker)
                                        <span class="muted">Hanya lihat</span>
                                    @elseif($workflow && $isMaker && ! $isChecker && ! $canEdit && ! $canSubmit)
                                        @if($status === $workflow['paid_status'])
                                            <span class="muted">Sudah Dibayar</span>
                                        @elseif($status === $workflow['approved_status'])
                                            <span class="muted">Menunggu Pembayaran</span>
                                        @else
                                            <span class="muted">Menunggu Finance</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($d['fields']) + 1 }}" class="empty">
                                Belum ada data {{ strtolower($d['singular']) }}.
                                @if(auth()->user()->role !== 'staff')
                                    Silakan tambahkan data pertama menggunakan tombol di atas.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            @include('components.pagination', [
                'paginator' => $rows
            ])
        </div>
    </section>
@endsection
