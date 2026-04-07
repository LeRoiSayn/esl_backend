<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Student Report</title>
    <style>
      body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; background: #f3f4f6; margin: 0; padding: 0; }
      .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 32px 36px 40px; }
      /* Header */
      .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 18px; margin-bottom: 22px; }
      .uni { display: flex; align-items: center; gap: 14px; }
      .logo { width: 64px; height: 64px; object-fit: contain; flex-shrink: 0; }
      .uni-name { font-size: 16px; font-weight: 700; color: #111827; line-height: 1.2; }
      .doc-title { font-size: 12px; font-weight: 700; color: #269c6d; margin-top: 4px; }
      .doc-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
      .meta { font-size: 11px; color: #374151; text-align: right; line-height: 1.8; white-space: nowrap; }
      /* Student info strip */
      .info-strip { display: flex; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 22px; }
      .info-cell { flex: 1; padding: 10px 14px; border-right: 1px solid #e5e7eb; }
      .info-cell:last-child { border-right: none; }
      .info-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
      .info-val { font-size: 12px; font-weight: 700; color: #111827; }
      /* Section headings */
      .section-title { margin: 22px 0 12px; font-size: 11px; font-weight: 700; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
      /* Curriculum labels */
      .level-hdr { font-size: 13px; font-weight: 700; color: #111827; margin: 18px 0 8px; padding: 6px 12px; background: #f9fafb; border-left: 4px solid #269c6d; }
      .sem-hdr { font-size: 11px; font-weight: 600; color: #374151; margin: 10px 0 5px; padding-left: 2px; }
      /* Tables */
      table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
      th { background: #f9fafb; color: #374151; padding: 7px 8px; text-align: left; font-size: 10px; font-weight: 700; border: 1px solid #e5e7eb; text-transform: uppercase; letter-spacing: .03em; }
      th.num { text-align: right; }
      td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; color: #111827; }
      td.num { text-align: right; }
      tr:nth-child(even) td { background: #f9fafb; }
      .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 600; border: 1px solid #d1d5db; background: #f9fafb; color: #374151; }
      .pill-pass { border-color: #86efac; background: #f0fdf4; color: #15803d; }
      .pill-fail { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
      .pill-prog { border-color: #fde047; background: #fefce8; color: #a16207; }
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
            <img class="logo" src="{{ $logoDataUri }}" alt="ESL" />
          @endif
          <div>
            <div class="uni-name">{{ $universityName }}</div>
            <div class="doc-title">Relevé Académique — Curriculum Complet</div>
            <div class="doc-sub">Document officiel généré le {{ $payload['generated_at'] ?? '' }}</div>
          </div>
        </div>
        <div class="meta">
          <div><strong>{{ $student['full_name'] ?? 'N/A' }}</strong></div>
          <div>Matricule : {{ $student['student_id'] ?? 'N/A' }}</div>
          <div>{{ $student['department'] ?? 'N/A' }}</div>
          <div>Niveau : {{ $student['level'] ?? 'N/A' }}@if(!empty($student['current_semester'])) — Sem. {{ $student['current_semester'] }}@endif</div>
        </div>
      </div>

      {{-- Student info strip --}}
      <div class="info-strip">
        <div class="info-cell">
          <div class="info-lbl">Étudiant</div>
          <div class="info-val">{{ $student['full_name'] ?? 'N/A' }}</div>
        </div>
        <div class="info-cell">
          <div class="info-lbl">Matricule</div>
          <div class="info-val">{{ $student['student_id'] ?? 'N/A' }}</div>
        </div>
        <div class="info-cell">
          <div class="info-lbl">Niveau</div>
          <div class="info-val">{{ $student['level'] ?? 'N/A' }}@if(!empty($student['current_semester'])) — Sem. {{ $student['current_semester'] }}@endif</div>
        </div>
        <div class="info-cell">
          <div class="info-lbl">Département</div>
          <div class="info-val">{{ $student['department'] ?? 'N/A' }}</div>
        </div>
      </div>

      <div class="section-title">Tous les cours du programme</div>

      @foreach($grouped as $level => $semesters)
        <div class="level-hdr">{{ $level }}</div>
        @foreach($semesters as $sem => $courses)
          <div class="sem-hdr">Semestre {{ $sem }}</div>
          <table>
            <thead>
              <tr>
                <th style="width:80px">Code</th>
                <th>Cours</th>
                <th style="width:60px;text-align:center">Crédits</th>
                <th style="width:100px">Statut</th>
                <th style="width:110px">Note</th>
              </tr>
            </thead>
            <tbody>
              @foreach($courses as $c)
                <tr>
                  <td style="font-family:monospace;font-size:10px">{{ $c['code'] ?? '' }}</td>
                  <td>{{ $c['name'] ?? '' }}</td>
                  <td style="text-align:center">{{ $c['credits'] ?? '' }}</td>
                  <td>
                    @php $st = $c['status'] ?? 'not_taken'; @endphp
                    <span class="pill {{ $st === 'passed' ? 'pill-pass' : ($st === 'failed' ? 'pill-fail' : ($st === 'in_progress' ? 'pill-prog' : '')) }}">
                      @if($st === 'passed') Validé
                      @elseif($st === 'failed') Ajourné
                      @elseif($st === 'in_progress') En cours
                      @else Non suivi
                      @endif
                    </span>
                  </td>
                  <td>
                    @if(!empty($c['grade']))
                      <strong>{{ $c['grade']['final_grade'] ?? '' }}/100</strong>
                      @if(!empty($c['grade']['letter_grade']))
                        &nbsp;<span style="color:#6b7280">({{ $c['grade']['letter_grade'] }})</span>
                      @endif
                    @else
                      <span style="color:#9ca3af">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endforeach
      @endforeach

      {{-- Document 2: Financial --}}
      <div class="page-break"></div>

      <div class="header">
        <div class="uni">
          @if($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="ESL" />
          @endif
          <div>
            <div class="uni-name">{{ $universityName }}</div>
            <div class="doc-title">Relevé Financier — par Année Académique</div>
            <div class="doc-sub">Document officiel généré le {{ $payload['generated_at'] ?? '' }}</div>
          </div>
        </div>
        <div class="meta">
          <div><strong>{{ $student['full_name'] ?? 'N/A' }}</strong></div>
          <div>Matricule : {{ $student['student_id'] ?? 'N/A' }}</div>
          <div>{{ $student['department'] ?? 'N/A' }}</div>
          <div>Niveau : {{ $student['level'] ?? 'N/A' }}</div>
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
                    $cls = $st === 'paid' ? 'pill-pass' : ($st === 'partial' ? 'pill-prog' : 'pill-fail');
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

      <div style="margin-top:32px;padding-top:10px;border-top:1px solid #e5e7eb;text-align:center;font-size:9px;color:#9ca3af">
        Document officiel — {{ $universityName }} — Généré le {{ $payload['generated_at'] ?? '' }}
      </div>
    </div>
  </body>
</html>

