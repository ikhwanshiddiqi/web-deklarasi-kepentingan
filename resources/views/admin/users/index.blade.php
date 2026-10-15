@extends('layouts.app')

@section('title', 'Kelola Akun')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Kelola Akun</h1>
        <p class="mt-1 text-sm text-gray-600">
            Daftar akun pengguna. Admin dapat mengaktifkan atau menonaktifkan akun.
        </p>
    </div>

    @if (session('status'))
        <div role="alert" class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div
        class="mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-gray-600">
            @if ($users->total() > 0)
                Menampilkan {{ $users->firstItem() }}-{{ $users->lastItem() }} dari
                {{ $users->total() }} data
            @else
                Tidak ada data
            @endif
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2 text-sm text-gray-600">
            <label for="per_page" class="font-medium text-gray-700">Tampil</label>
            <select id="per_page" name="per_page" onchange="this.form.submit()"
                class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                @foreach ([10, 25, 50, 100] as $value)
                    <option value="{{ $value }}" {{ (int) $perPage === $value ? 'selected' : '' }}>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
            <span>per halaman</span>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">No</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">NIK</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Dibuat</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-gray-600">{{ $users->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->nik }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $user->role === 'admin' ? 'Admin' : 'Karyawan' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span
                                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                    Aktif
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                    Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $user->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-gray-400">Akun Anda</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">
                                    @if ($user->is_active)
                                        <button type="submit"
                                            class="rounded-md border border-red-200 bg-white px-3 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50">
                                            Nonaktifkan
                                        </button>
                                    @else
                                        <button type="submit"
                                            class="rounded-md border border-blue-200 bg-white px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50">
                                            Aktifkan
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                            Belum ada akun.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->appends(['per_page' => $perPage])->links() }}
    </div>
@endsection
