<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Relevé Académique</title>
    <style>
      body {
        font-family: DejaVu Sans, Arial, sans-serif;
        color: #111827;
        background: #f3f4f6;
        font-size: 12px;
        margin: 0;
        padding: 0;
      }
      .page {
        max-width: 900px;
        margin: 0 auto;
        background: #fff;
        padding: 32px 36px 40px;
      }

      /* Header */
      .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 18px;
        margin-bottom: 22px;
      }
      .uni { display: flex; align-items: center; gap: 14px; }
      .logo { width: 64px; height: 64px; object-fit: contain; flex-shrink: 0; }
      .uni-name { font-size: 16px; font-weight: 700; color: #111827; line-height: 1.2; }
      .doc-title { font-size: 12px; font-weight: 700; color: #269c6d; margin-top: 4px; }
      .doc-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
      .meta { font-size: 11px; color: #374151; text-align: right; line-height: 1.8; white-space: nowrap; }

      /* Student info strip */
      .info-strip {
        display: flex;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 22px;
      }
      .info-cell { flex: 1; padding: 10px 14px; border-right: 1px solid #e5e7eb; }
      .info-cell:last-child { border-right: none; }
      .info-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
      .info-val { font-size: 12px; font-weight: 700; color: #111827; }

      /* Section heading */
      .section-title {
        margin: 22px 0 12px;
        font-size: 11px;
        font-weight: 700;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: .05em;
      }

      /* Level and semester labels */
      .level-hdr {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        margin: 18px 0 8px;
        padding: 6px 12px;
        background: #f9fafb;
        border-left: 4px solid #269c6d;
      }
      .sem-hdr {
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        margin: 10px 0 5px;
        padding-left: 2px;
      }

      /* Tables */
      table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
      th {
        background: #f9fafb;
        color: #374151;
        padding: 7px 8px;
        text-align: left;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid #e5e7eb;
        text-transform: uppercase;
        letter-spacing: .03em;
      }
      td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; color: #111827; }
      tr:nth-child(even) td { background: #f9fafb; }

      /* Status pill */
      .pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: #374151;
      }
      .pill-pass { border-color: #86efac; background: #f0fdf4; color: #15803d; }
      .pill-fail { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
      .pill-prog { border-color: #fde047; background: #fefce8; color: #a16207; }

      /* Footer */
      .footer {
        margin-top: 32px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
        text-align: center;
        font-size: 9px;
        color: #9ca3af;
      }

      @media print {
        body { background: #fff; }
        .page { margin: 0; padding: 24px 28px; max-width: 100%; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      }
    </style>
  </head>
  <body>
    @php
      $student = $payload['student'] ?? [];
      $curriculum = $payload['academic']['curriculum'] ?? [];
      $grouped = [];
      foreach ($curriculum as $c) {
        $level = $c['level'] ?? '—';
        $sem = $c['semester'] ?? '—';
        if (!isset($grouped[$level])) $grouped[$level] = [];
        if (!isset($grouped[$level][$sem])) $grouped[$level][$sem] = [];
        $grouped[$level][$sem][] = $c;
      }
    @endphp

    <div class="page">
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
        </div>
      </div>

      {{-- Student summary strip --}}
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
          <div class="info-val">
            {{ $student['level'] ?? 'N/A' }}@if(!empty($student['current_semester'])) — Sem. {{ $student['current_semester'] }}@endif
          </div>
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

      <div class="footer">
        Document officiel — {{ $universityName }} — Généré le {{ $payload['generated_at'] ?? '' }}
      </div>
    </div>
  </body>
</html>
