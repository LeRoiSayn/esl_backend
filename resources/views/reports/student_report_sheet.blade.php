<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Student Report</title>
    <style>
      body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; }
      .sheet { width: 100%; margin: 0 auto; padding: 24px; }
      /* Header */
      .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 18px; }
      .uni { display: flex; align-items: center; gap: 12px; }
      .logo { width: 72px; height: 72px; object-fit: contain; }
      .uni-name { font-size: 16px; font-weight: 700; color: #111827; }
      .doc-title { font-size: 13px; font-weight: 700; color: #269c6d; margin-top: 3px; }
      .meta { font-size: 11px; color: #374151; text-align: right; line-height: 1.7; }
      /* Section headings */
      .section-title { margin: 18px 0 12px; font-size: 13px; font-weight: 800; color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
      /* Curriculum tables */
      table { width: 100%; border-collapse: collapse; }
      th { background: #f9fafb; color: #111827; padding: 7px 8px; text-align: left; font-size: 10px; font-weight: 700; border: 1px solid #e5e7eb; }
      th.num { text-align: right; }
      td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; color: #111827; }
      td.num { text-align: right; }
      tr:nth-child(even) td { background: #f9fafb; }
      .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; border: 1px solid #e5e7eb; }
      /* Year box (financial section) */
      .year-box { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 18px; overflow: hidden; }
      .year-hdr { padding: 9px 14px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
      .year-hdr-title { font-size: 13px; font-weight: 700; color: #111827; }
      .year-summary { display: flex; border-bottom: 1px solid #e5e7eb; }
      .summary-cell { flex: 1; padding: 9px 14px; border-right: 1px solid #e5e7eb; }
      .summary-cell:last-child { border-right: none; }
      .summary-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
      .summary-val { font-size: 13px; font-weight: 700; margin-top: 3px; }
      .pill-g { background: #f0fdf4; color: #15803d; border-color: #86efac; }
      .pill-y { background: #fefce8; color: #a16207; border-color: #fde047; }
      .pill-r { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
      .tx-hdr { padding: 7px 14px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 10px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .05em; }
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

      <div class="section-title">Frais &amp; Paiements par Année Académique</div>

      @if(empty($financeYears))
        <div style="font-size: 12px; color: #6b7280;">Aucune donnée financière.</div>
      @else
        @foreach($financeYears as $year)
          @php
            $total   = $year['summary']['total']   ?? 0;
            $paid    = $year['summary']['paid']     ?? 0;
            $balance = $year['summary']['balance']  ?? 0;
          @endphp
          <div class="year-box">
            <div class="year-hdr">
              <div class="year-hdr-title">Année académique : {{ $year['academic_year'] ?? '' }}</div>
            </div>
            <div class="year-summary">
              <div class="summary-cell">
                <div class="summary-lbl">Total dû</div>
                <div class="summary-val">{{ number_format($total) }} FCFA</div>
              </div>
              <div class="summary-cell">
                <div class="summary-lbl">Payé</div>
                <div class="summary-val" style="color:#15803d">{{ number_format($paid) }} FCFA</div>
              </div>
              <div class="summary-cell">
                <div class="summary-lbl">Solde restant</div>
                <div class="summary-val" style="color:{{ $balance > 0 ? '#b91c1c' : '#15803d' }}">{{ number_format($balance) }} FCFA</div>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>Frais</th>
                  <th class="num" style="width:130px;">Montant</th>
                  <th class="num" style="width:130px;">Payé</th>
                  <th class="num" style="width:130px;">Solde</th>
                  <th style="width:110px;">Échéance</th>
                  <th style="width:90px;">Statut</th>
                </tr>
              </thead>
              <tbody>
                @foreach(($year['fees'] ?? []) as $f)
                  @php
                    $st  = $f['status'] ?? '';
                    $cls = $st === 'paid' ? 'pill-g' : ($st === 'partial' ? 'pill-y' : 'pill-r');
                    $lbl = $st === 'paid' ? 'Payé' : ($st === 'partial' ? 'Partiel' : ucfirst($st));
                  @endphp
                  <tr>
                    <td>{{ $f['fee_type'] ?? '' }}</td>
                    <td class="num">{{ number_format($f['amount'] ?? 0) }} FCFA</td>
                    <td class="num" style="color:#15803d">{{ number_format($f['paid'] ?? 0) }} FCFA</td>
                    <td class="num" style="font-weight:600;color:{{ ($f['balance'] ?? 0) > 0 ? '#b91c1c' : '#15803d' }}">{{ number_format($f['balance'] ?? 0) }} FCFA</td>
                    <td>{{ $f['due_date'] ?? '—' }}</td>
                    <td><span class="pill {{ $cls }}">{{ $lbl }}</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            @if(!empty($year['transactions']))
              <div class="tx-hdr">Transactions enregistrées</div>
              <table>
                <thead>
                  <tr>
                    <th>Référence</th>
                    <th>Méthode</th>
                    <th class="num" style="width:130px;">Montant</th>
                    <th style="width:160px;">Date</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach(($year['transactions'] ?? []) as $tx)
                    <tr>
                      <td style="font-family: monospace; font-size: 10px;">{{ $tx['reference'] ?? '' }}</td>
                      <td>{{ $tx['payment_method'] ?? '' }}</td>
                      <td class="num" style="color:#15803d;font-weight:600">{{ number_format($tx['amount'] ?? 0) }} FCFA</td>
                      <td style="color:#6b7280">{{ $tx['created_at'] ?? '' }}</td>
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

