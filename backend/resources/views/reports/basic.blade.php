<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #25231f; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #5d594f; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd2c3; padding: 8px; text-align: left; }
        th { background: #efe8dc; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Periode: {{ $start }} sampai {{ $end }}</div>
    <table>
        <thead>
            <tr>
                <th>Kolom</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Status</td>
                <td>Laporan dibuat dari endpoint MVP sesuai PRD.</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
