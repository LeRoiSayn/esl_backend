<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Financial Sheet</title>
    <style>
      body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; }
      .sheet { width: 100%; margin: 0 auto; padding: 24px; }
      .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 16px; }
      .uni { display: flex; align-items: center; gap: 12px; }
      .logo { width: 72px; height: 72px; object-fit: contain; }
      .uni-name { font-size: 16px; font-weight: 700; }
      .doc-title { font-size: 14px; font-weight: 700; margin-top: 2px; }
      .meta { font-size: 12px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; vertical-align: top; }
      th { background: #f9fafb; font-weight: 700; }
      .section-title { margin: 18px 0 10px; font-size: 13px; font-weight: 800; }
      @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
  </head>
  <body>
    @php
      $student = $payload['student'] ?? [];
      $financeYears = $payload['finance']['years'] ?? [];
    @endphp

    <div class="sheet">
      <div class="header">
        <div class="uni">
          @if($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="University Logo" />
          @endif
          <div>
            <div class="uni-name">{{ $universityName }}</div>
            <div class="doc-title">Financial Sheet (by academic year)</div>
          </div>
        </div>
        <div class="meta">
          <div><strong>Student:</strong> {{ $student['full_name'] ?? 'N/A' }}</div>
          <div><strong>Student ID:</strong> {{ $student['student_id'] ?? 'N/A' }}</div>
          <div><strong>Department:</strong> {{ $student['department'] ?? 'N/A' }}</div>
          <div><strong>Level:</strong> {{ $student['level'] ?? 'N/A' }}</div>
          <div><strong>Generated:</strong> {{ $payload['generated_at'] ?? '' }}</div>
        </div>
      </div>

      <div class="section-title">Fees & Payments Summary</div>

      @if(empty($financeYears))
        <div style="font-size: 12px; color: #6b7280;">No financial data.</div>
      @else
        @foreach($financeYears as $year)
          <div style="margin-top: 14px;">
            <div style="font-weight: 800; margin-bottom: 6px;">Academic Year: {{ $year['academic_year'] ?? '' }}</div>
            <div style="font-size: 12px; margin-bottom: 10px; color: #374151;">
              Total: {{ number_format($year['summary']['total'] ?? 0) }} FCFA
              • Paid: {{ number_format($year['summary']['paid'] ?? 0) }} FCFA
              • Balance: {{ number_format($year['summary']['balance'] ?? 0) }} FCFA
            </div>
            <table>
              <thead>
                <tr>
                  <th>Fee</th>
                  <th style="width: 120px;">Amount</th>
                  <th style="width: 120px;">Paid</th>
                  <th style="width: 120px;">Balance</th>
                  <th style="width: 110px;">Due</th>
                  <th style="width: 90px;">Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach(($year['fees'] ?? []) as $f)
                  <tr>
                    <td>{{ $f['fee_type'] ?? '' }}</td>
                    <td>{{ number_format($f['amount'] ?? 0) }} FCFA</td>
                    <td>{{ number_format($f['paid'] ?? 0) }} FCFA</td>
                    <td>{{ number_format($f['balance'] ?? 0) }} FCFA</td>
                    <td>{{ $f['due_date'] ?? '' }}</td>
                    <td>{{ $f['status'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            @if(!empty($year['transactions']))
              <div style="margin-top: 10px; font-weight: 700; font-size: 12px;">Online Transactions</div>
              <table style="margin-top: 6px;">
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Method</th>
                    <th style="width: 120px;">Amount</th>
                    <th style="width: 160px;">Date</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach(($year['transactions'] ?? []) as $tx)
                    <tr>
                      <td style="font-family: monospace;">{{ $tx['reference'] ?? '' }}</td>
                      <td>{{ $tx['payment_method'] ?? '' }}</td>
                      <td>{{ number_format($tx['amount'] ?? 0) }} FCFA</td>
                      <td>{{ $tx['created_at'] ?? '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @endif
          </div>
        @endforeach
      @endif
    </div>
  </body>
</html>

