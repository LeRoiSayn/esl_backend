<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Student Report</title>
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
      .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; border: 1px solid #e5e7eb; }
      .page-break { page-break-before: always; margin-top: 24px; }
      @media print {
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body>
    @php
      $student = $payload['student'] ?? [];
      $curriculum = $payload['academic']['curriculum'] ?? [];
      $financeYears = $payload['finance']['years'] ?? [];
      $grouped = [];
      foreach ($curriculum as $c) {
        $level = $c['level'] ?? '—';
        $sem = $c['semester'] ?? '—';
        if (!isset($grouped[$level])) $grouped[$level] = [];
        if (!isset($grouped[$level][$sem])) $grouped[$level][$sem] = [];
        $grouped[$level][$sem][] = $c;
      }
    @endphp

    <div class="sheet">
      {{-- Document 1: Academic / Curriculum --}}
      <div class="header">
        <div class="uni">
          @if($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="University Logo" />
          @endif
          <div>
            <div class="uni-name">{{ $universityName }}</div>
            <div class="doc-title">Student Academic Curriculum Report</div>
          </div>
        </div>
        <div class="meta">
          <div><strong>Student:</strong> {{ $student['full_name'] ?? 'N/A' }}</div>
          <div><strong>Student ID:</strong> {{ $student['student_id'] ?? 'N/A' }}</div>
          <div><strong>Department:</strong> {{ $student['department'] ?? 'N/A' }}</div>
          <div><strong>Level:</strong> {{ $student['level'] ?? 'N/A' }} @if(!empty($student['current_semester'])) (Sem {{ $student['current_semester'] }}) @endif</div>
          <div><strong>Generated:</strong> {{ $payload['generated_at'] ?? '' }}</div>
        </div>
      </div>

      <div class="section-title">Curriculum Coverage (All programme courses)</div>

      @foreach($grouped as $level => $semesters)
        <div style="margin-top: 12px;">
          <div style="font-weight: 800; margin-bottom: 6px;">{{ $level }}</div>
          @foreach($semesters as $sem => $courses)
            <div style="margin: 10px 0;">
              <div style="font-weight: 700; margin-bottom: 6px;">Semester {{ $sem }}</div>
              <table>
                <thead>
                  <tr>
                    <th style="width: 90px;">Code</th>
                    <th>Course</th>
                    <th style="width: 70px;">Credits</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 120px;">Grade</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($courses as $c)
                    <tr>
                      <td style="font-family: monospace;">{{ $c['code'] ?? '' }}</td>
                      <td>
                        {{ $c['name'] ?? '' }}
                      </td>
                      <td>{{ $c['credits'] ?? '' }}</td>
                      <td>
                        @php
                          $st = $c['status'] ?? 'not_taken';
                        @endphp
                        <span class="pill">
                          @if($st === 'passed') Passed
                          @elseif($st === 'failed') Failed
                          @elseif($st === 'in_progress') In progress
                          @else Not taken
                          @endif
                        </span>
                      </td>
                      <td>
                        @if(!empty($c['grade']))
                          {{ $c['grade']['final_grade'] ?? '' }}/100
                          @if(!empty($c['grade']['letter_grade']))
                            ({{ $c['grade']['letter_grade'] }})
                          @endif
                        @else
                          —
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endforeach
        </div>
      @endforeach

      {{-- Document 2: Financial --}}
      <div class="page-break"></div>

      <div class="header">
        <div class="uni">
          @if($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="University Logo" />
          @endif
          <div>
            <div class="uni-name">{{ $universityName }}</div>
            <div class="doc-title">Student Financial Report (by academic year)</div>
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

