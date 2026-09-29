{{--
    Kartu ringkasan dashboard dengan tata letak yang ditulis sendiri.

    Seluruh pengaturan tampilan ada di satu baris kelas pada div di bawah:
      - grid-cols-1            : satu kolom di ponsel
      - md:grid-cols-2         : dua kolom mulai 768px
      - xl:grid-cols-4         : empat kolom mulai 1280px
      - gap-8                  : jarak antar kartu (2rem). Ganti ke gap-6 atau gap-10 sesuai selera.

    Tidak ada variabel CSS Filament yang terlibat, jadi mengubah angka di sini cukup dengan
    `npm run build` dan langsung terlihat.
--}}
<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->kartu() as $kartu)
            <div class="flex h-full flex-col gap-3 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        :icon="$kartu['ikon']"
                        @class(['h-5 w-5 shrink-0', $this->kelasWarna($kartu['warna'])])
                    />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $kartu['label'] }}
                    </span>
                </div>

                <div class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $kartu['nilai'] }}
                </div>

                <div @class(['text-sm', $this->kelasWarna($kartu['warna'])])>
                    {{ $kartu['keterangan'] }}
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
