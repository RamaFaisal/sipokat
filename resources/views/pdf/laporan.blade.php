{{--
    Cetakan PDF bersama untuk laporan bertabel tunggal (akan kedaluwarsa, rekap stok per obat,
    pembelian per PBF, hasil stok opname).

    Dua laporan pertama (Rekap dan Fast/Slow Moving) punya cetakannya sendiri karena susunannya
    berbeda: beberapa blok ringkasan dan dua tabel. Yang di sini bentuknya seragam, jadi cukup satu
    berkas: judul, periode, ringkasan, satu tabel.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 18mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        .doc-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .doc-sub {
            font-size: 11px;
            margin-top: 2px;
        }

        .doc-meta {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
            margin-bottom: 10px;
        }

        table.ringkasan {
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.ringkasan td {
            padding: 3px 14px 3px 0;
            font-size: 10px;
        }

        table.ringkasan .label { color: #6b7280; }
        table.ringkasan .value { font-weight: bold; }

        table.isi {
            width: 100%;
            border-collapse: collapse;
        }

        table.isi th {
            background: #e5e7eb;
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        table.isi td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
        }

        table.isi tr:nth-child(even) td { background: #f9fafb; }

        .angka { text-align: right; }

        .kaki {
            margin-top: 12px;
            font-size: 8px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="doc-title">{{ $judul }}</div>
<div class="doc-sub">Apotek Anugrah Husada - {{ $periode }}</div>
<div class="doc-meta">Dicetak {{ $printedAt }}</div>

@if (! empty($ringkasan))
    <table class="ringkasan">
        @foreach ($ringkasan as $label => $nilai)
            <tr>
                <td class="label">{{ $label }}</td>
                <td class="value">{{ $nilai }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table class="isi">
    <thead>
        <tr>
            @foreach ($kolom as $judulKolom)
                <th>{{ $judulKolom }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($baris as $isi)
            <tr>
                @foreach (array_values($isi) as $nilai)
                    <td @class(['angka' => is_numeric($nilai)])>{{ $nilai }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="kaki">SIPOKAT - Sistem Inventory Obat Apotek Anugrah Husada</div>

</body>
</html>
