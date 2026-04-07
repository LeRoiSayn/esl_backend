<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Financial Sheet</title>
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
      /* Section heading */
      .section-title { margin: 22px 0 12px; font-size: 11px; font-weight: 700; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
      /* Year box */
      .year-box { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 18px; overflow: hidden; }
      .year-hdr { padding: 9px 14px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
      .year-hdr-title { font-size: 13px; font-weight: 700; color: #111827; }
      /* Summary strip */
      .year-summary { display: flex; border-bottom: 1px solid #e5e7eb; }
      .summary-cell { flex: 1; padding: 9px 14px; border-right: 1px solid #e5e7eb; }
      .summary-cell:last-child { border-right: none; }
      .summary-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
      .summary-val { font-size: 13px; font-weight: 700; margin-top: 3px; }
      /* Tables */
      table { width: 100%; border-collapse: collapse; }
      th { background: #f9fafb; color: #111827; padding: 7px 8px; text-align: left; font-size: 10px; font-weight: 700; border: 1px solid #e5e7eb; }
      th.num { text-align: right; }
      td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; color: #111827; }
      td.num { text-align: right; }
      tr:nth-child(even) td { background: #f9fafb; }
      /* Status pills */
      .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 600; border: 1px solid; }
      .pill-g { background: #f0fdf4; color: #15803d; border-color: #86efac; }
      .pill-y { background: #fefce8; color: #a16207; border-color: #fde047; }
      .pill-r { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
      /* Transactions sub-section */
      .tx-hdr { padding: 7px 14px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 10px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .05em; }
      .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 9px; color: #9ca3af; }
      @media print {
        body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { margin: 0; padding: 24px 28px; max-width: 100%; }
      }
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
            {{-- Year title --}}
            <div class="year-hdr">
              <div class="year-hdr-title">Année académique : {{ $year['academic_year'] ?? '' }}</div>
            </div>

            {{-- Summary strip --}}
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

            {{-- Fees detail table --}}
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

            {{-- Online transactions (optional) --}}
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

      <div class="footer">Document généré automatiquement — {{ $universityName }} — {{ $payload['generated_at'] ?? '' }}</div>
    </div>
  </body>
</html>
