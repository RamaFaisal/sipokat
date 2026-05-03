<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Receive Order - {{ $numberPo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }
        .container {
            padding: 20px 30px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #059669;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 65%;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 35%;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #047857;
            margin-bottom: 2px;
        }
        .company-info {
            font-size: 10px;
            color: #666;
        }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
        }
        .doc-number {
            font-size: 14px;
            color: #555;
            margin-top: 2px;
        }

        /* Info Section */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table thead th {
            background-color: #059669;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table thead th:first-child {
            border-radius: 4px 0 0 0;
        }
        .items-table thead th:last-child {
            border-radius: 0 4px 0 0;
            text-align: right;
        }
        .items-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .items-table tbody tr:nth-child(even) {
            background-color: #f0fdf4;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }

        /* Summary */
        .summary-section {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .summary-left {
            display: table-cell;
            width: 55%;
            vertical-align: top;
        }
        .summary-right {
            display: table-cell;
            width: 45%;
            vertical-align: top;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 5px 10px;
            font-size: 11px;
        }
        .summary-table .label {
            color: #666;
            text-align: right;
            width: 55%;
        }
        .summary-table .value {
            text-align: right;
            font-weight: bold;
            width: 45%;
        }
        .summary-table .grand-total td {
            border-top: 2px solid #059669;
            font-size: 13px;
            font-weight: bold;
            color: #047857;
            padding-top: 8px;
        }

        /* Notes */
        .notes-section {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #f0fdf4;
            border-left: 3px solid #059669;
            border-radius: 0 4px 4px 0;
        }
        .notes-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 4px;
        }
        .notes-text {
            font-size: 11px;
            color: #555;
        }

        /* Reference Badge */
        .po-reference {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        .footer-col {
            display: table-cell;
            width: 50%;
            text-align: center;
        }
        .sign-label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 50px;
        }
        .sign-line {
            border-top: 1px solid #333;
            width: 150px;
            margin: 50px auto 5px;
        }
        .sign-name {
            font-size: 11px;
            font-weight: bold;
        }

        /* Print info */
        .print-info {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #bbb;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                <div class="company-name">SIPOKAT</div>
                <div class="company-info">Sistem Informasi Apotek</div>
            </div>
            <div class="header-right">
                <div class="doc-title">Receive Order</div>
                <div class="doc-number">{{ $numberPo }}</div>
            </div>
        </div>

        {{-- Info Section --}}
        <div class="info-section">
            <div class="info-col">
                <div class="info-label">Supplier</div>
                <div class="info-value">{{ $record->supplier->name ?? '-' }}</div>

                <div class="info-label">Tanggal Penerimaan</div>
                <div class="info-value">{{ $record->receive_date?->format('d F Y') ?? '-' }}</div>
            </div>
            <div class="info-col">
                <div class="info-label">Referensi PO</div>
                <div class="info-value">
                    <span class="po-reference">{{ $record->purchaseOrder->po_number ?? '-' }}</span>
                </div>

                <div class="info-label">Status</div>
                <div class="info-value">{{ ucfirst($record->status ?? '-') }}</div>
            </div>
        </div>

        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 40%;">Nama Obat</th>
                    <th class="text-center" style="width: 15%;">Qty Diterima</th>
                    <th class="text-right" style="width: 20%;">Harga</th>
                    <th class="text-right" style="width: 20%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $totalAmount = 0; @endphp
                @foreach ($record->items as $index => $item)
                    @php $subtotal = $item->qty * $item->price; $totalAmount += $subtotal; @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $item->medicine->name ?? '-' }}
                            @if($item->medicine?->dosage)
                                <br><small style="color: #888;">{{ $item->medicine->dosage }}</small>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->qty }}</td>
                        <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Summary --}}
        <div class="summary-section">
            <div class="summary-left">
                @if($record->description)
                    <div class="notes-section">
                        <div class="notes-title">Keterangan</div>
                        <div class="notes-text">{{ $record->description }}</div>
                    </div>
                @endif
            </div>
            <div class="summary-right">
                <table class="summary-table">
                    <tr class="grand-total">
                        <td class="label">Total Penerimaan</td>
                        <td class="value">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Signature --}}
        <div class="footer">
            <div class="footer-col">
                <div class="sign-label">Diterima Oleh</div>
                <div class="sign-line"></div>
                <div class="sign-name">{{ $record->receiver->name ?? '________________' }}</div>
            </div>
            <div class="footer-col">
                <div class="sign-label">Mengetahui</div>
                <div class="sign-line"></div>
                <div class="sign-name">________________</div>
            </div>
        </div>

        <div class="print-info">
            Dicetak pada {{ now()->format('d/m/Y H:i') }} — SIPOKAT
        </div>
    </div>
</body>
</html>
