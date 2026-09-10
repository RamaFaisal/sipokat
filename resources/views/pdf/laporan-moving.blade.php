<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Fast / Slow Moving</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.5pt;
            color: #1a1a1a;
            margin: 0;
        }

        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 2mm 0;
        }

        .doc-sub {
            text-align: center;
            font-size: 9pt;
            color: #444;
            margin: 0 0 1mm 0;
        }

        .doc-meta {
            text-align: center;
            font-size: 7.5pt;
            color: #777;
            margin: 0 0 5mm 0;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        table.summary td {
            width: 25%;
            border: 0.6pt solid #c9c9c9;
            padding: 2mm 2.5mm;
            vertical-align: top;
        }

        .summary .label {
            font-size: 7pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }

        .summary .value {
            font-size: 10.5pt;
            font-weight: bold;
            padding-top: 0.8mm;
        }

        h2.section {
            font-size: 10pt;
            margin: 0 0 1.5mm 0;
            padding: 1.5mm 2.5mm;
            color: #fff;
            background: #1f4e78;
        }

        h2.section .hint {
            font-weight: normal;
            font-size: 7.5pt;
            color: #d6e2ee;
        }

        .block { margin-bottom: 6mm; }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            background: #e8edf3;
            color: #1f4e78;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 1.6mm 1.5mm;
            border: 0.5pt solid #b8c6d6;
            text-align: center;
        }

        table.data td {
            border: 0.5pt solid #cfcfcf;
            padding: 1.4mm 1.5mm;
        }

        table.data tbody tr:nth-child(even) td {
            background: #f5f7fa;
        }

        .num { text-align: right; }
        .ctr { text-align: center; }

        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .empty {
            padding: 4mm;
            color: #888;
            border: 0.5pt solid #cfcfcf;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="doc-title">LAPORAN FAST / SLOW MOVING</div>
<div class="doc-sub">Apotek Anugrah Husada &mdash; Periode {{ $meta['periode'] }}</div>
<div class="doc-meta">
    Rentang {{ $meta['days'] }} hari &nbsp;·&nbsp; Menampilkan maksimal {{ $topN }} obat per kategori
    &nbsp;·&nbsp; Dicetak {{ $printedAt }}
</div>

<table class="summary">
    <tr>
        <td>
            <div class="label">Obat Aktif</div>
            <div class="value">{{ number_format($meta['total_obat_aktif'], 0, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Ada Transaksi</div>
            <div class="value">{{ number_format($meta['obat_dengan_transaksi'], 0, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Tanpa Transaksi</div>
            <div class="value">{{ number_format($meta['obat_tanpa_transaksi'], 0, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Rentang Analisis</div>
            <div class="value">{{ $meta['days'] }} hari</div>
        </td>
    </tr>
</table>

@foreach ($sections as $section)
    <div class="block">
        <h2 class="section">
            {{ $section['title'] }}
            <span class="hint">&mdash; {{ $section['hint'] }}</span>
        </h2>

        @if ($section['rows']->isEmpty())
            <div class="empty">Tidak ada obat pada kategori ini.</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 16%">Kode</th>
                        <th style="width: 26%">Nama Obat</th>
                        <th style="width: 13%">Kategori</th>
                        <th style="width: 8%">Satuan</th>
                        <th style="width: 9%">Stok</th>
                        <th style="width: 9%">Qty Terjual</th>
                        <th style="width: 9%">Demand/Bln</th>
                        <th style="width: 10%">Nilai Jual</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $r)
                        <tr>
                            <td>{{ $r['code'] }}</td>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ $r['category'] }}</td>
                            <td class="ctr">{{ $r['unit'] }}</td>
                            <td class="num">{{ number_format($r['current_stock'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($r['total_qty'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($r['monthly_avg'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($r['total_value'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

</body>
</html>
