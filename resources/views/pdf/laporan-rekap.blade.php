<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Rekap Penjualan &amp; Pembelian</title>
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
            margin-bottom: 5mm;
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

        .summary .note {
            font-size: 7pt;
            color: #888;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            background: #1f4e78;
            color: #fff;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 1.8mm 1.5mm;
            border: 0.5pt solid #1f4e78;
            text-align: center;
        }

        table.data td {
            border: 0.5pt solid #cfcfcf;
            padding: 1.4mm 1.5mm;
        }

        table.data tbody tr:nth-child(even) td {
            background: #f5f7fa;
        }

        table.data tfoot td {
            background: #ffe699;
            font-weight: bold;
            border: 0.5pt solid #b0b0b0;
        }

        .num { text-align: right; }
        .ctr { text-align: center; }

        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .empty {
            text-align: center;
            padding: 10mm;
            color: #888;
            border: 0.5pt solid #cfcfcf;
        }
    </style>
</head>
<body>

<div class="doc-title">LAPORAN REKAP PENJUALAN &amp; PEMBELIAN</div>
<div class="doc-sub">Apotek Anugrah Husada &mdash; Periode {{ $summary['periode'] }}</div>
<div class="doc-meta">
    Tipe: {{ $tipeLabel }} &nbsp;·&nbsp; Dicetak {{ $printedAt }}
</div>

<table class="summary">
    <tr>
        <td>
            <div class="label">Total Penjualan</div>
            <div class="value">Rp {{ number_format($summary['total_jual'], 0, ',', '.') }}</div>
            <div class="note">{{ number_format($summary['total_jual_qty'], 0, ',', '.') }} unit ·
                {{ number_format($summary['jumlah_transaksi_jual'], 0, ',', '.') }} transaksi</div>
        </td>
        <td>
            <div class="label">Total Pembelian</div>
            <div class="value">Rp {{ number_format($summary['total_beli'], 0, ',', '.') }}</div>
            <div class="note">{{ number_format($summary['total_beli_qty'], 0, ',', '.') }} unit ·
                {{ number_format($summary['jumlah_transaksi_beli'], 0, ',', '.') }} transaksi</div>
        </td>
        <td>
            <div class="label">Margin Kotor</div>
            <div class="value">Rp {{ number_format($summary['margin_kotor'], 0, ',', '.') }}</div>
            <div class="note">Penjualan &minus; HPP</div>
        </td>
        <td>
            <div class="label">Jenis Obat</div>
            <div class="value">{{ number_format($rows->count(), 0, ',', '.') }}</div>
            <div class="note">Obat dengan transaksi</div>
        </td>
    </tr>
</table>

@if ($rows->isEmpty())
    <div class="empty">Tidak ada transaksi pada periode ini.</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width: 13%">Kode</th>
                <th style="width: 20%">Nama Obat</th>
                <th style="width: 10%">Kategori</th>
                <th style="width: 6%">Qty Beli</th>
                <th style="width: 11%">Nilai Beli</th>
                <th style="width: 5%">Trx</th>
                <th style="width: 6%">Qty Jual</th>
                <th style="width: 11%">Nilai Jual</th>
                <th style="width: 5%">Trx</th>
                <th style="width: 13%">Margin Kotor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['code'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['category'] }}</td>
                    <td class="num">{{ number_format($r['beli_qty'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($r['beli_nilai'], 0, ',', '.') }}</td>
                    <td class="ctr">{{ $r['beli_transaksi'] }}</td>
                    <td class="num">{{ number_format($r['jual_qty'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($r['jual_nilai'], 0, ',', '.') }}</td>
                    <td class="ctr">{{ $r['jual_transaksi'] }}</td>
                    <td class="num">{{ number_format($r['margin_kotor'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAL</td>
                <td class="num">{{ number_format($summary['total_beli_qty'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['total_beli'], 0, ',', '.') }}</td>
                <td class="ctr">{{ $summary['jumlah_transaksi_beli'] }}</td>
                <td class="num">{{ number_format($summary['total_jual_qty'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($summary['total_jual'], 0, ',', '.') }}</td>
                <td class="ctr">{{ $summary['jumlah_transaksi_jual'] }}</td>
                <td class="num">{{ number_format($summary['margin_kotor'], 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
@endif

</body>
</html>
