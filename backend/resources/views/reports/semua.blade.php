<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #25231f; font-size: 11px; }
        h1 { font-size: 16px; margin-bottom: 4px; color: #0f4c47; }
        .meta { color: #5d594f; margin-bottom: 12px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ddd2c3; padding: 6px; text-align: left; }
        th { background: #efe8dc; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <h1>Laporan Prediksi Risiko</h1>
    <div class="meta">Periode: {{ $start }} sampai {{ $end }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($prediksi['columns'] as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($prediksi['rows'] as $row)
                <tr>
                    @foreach ($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($prediksi['columns']) }}">Belum ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h1>Laporan Kehadiran Posyandu</h1>
    <div class="meta">Periode: {{ $start }} sampai {{ $end }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($kehadiran['columns'] as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($kehadiran['rows'] as $row)
                <tr>
                    @foreach ($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($kehadiran['columns']) }}">Belum ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h1>Laporan Distribusi PMT</h1>
    <div class="meta">Periode: {{ $start }} sampai {{ $end }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($pmt['columns'] as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($pmt['rows'] as $row)
                <tr>
                    @foreach ($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($pmt['columns']) }}">Belum ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
