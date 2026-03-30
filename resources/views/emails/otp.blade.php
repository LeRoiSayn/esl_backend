<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Code OTP — ESL</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d6a9f 100%);padding:32px 40px;text-align:center;">
              <img src="{{ $message->embed($logoPath) }}" alt="ESL" width="80" height="80"
                   style="border-radius:50%;border:3px solid rgba(255,255,255,0.2);margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;" />
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.5px;">
                 École de Santé de Libreville
              </h1>
              <p style="margin:6px 0 0;color:#a8d4f5;font-size:13px;">ESL</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 40px 32px;">
              <p style="margin:0 0 8px;color:#374151;font-size:15px;">Bonjour <strong>{{ $user->first_name }}</strong>,</p>

              @if($type === 'login')
                <p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.6;">
                  Voici votre code de vérification pour vous connecter à votre compte ESL. Ce code est valable <strong>10 minutes</strong>.
                </p>
              @else
                <p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.6;">
                  Vous avez demandé une réinitialisation de votre mot de passe. Utilisez ce code pour continuer. Il est valable <strong>10 minutes</strong>.
                </p>
              @endif

              <!-- OTP Code -->
              <div style="text-align:center;margin:32px 0;">
                <div style="display:inline-block;background:#f0f7ff;border:2px dashed #2d6a9f;border-radius:12px;padding:20px 40px;">
                  <p style="margin:0 0 4px;color:#6b7280;font-size:11px;text-transform:uppercase;letter-spacing:2px;">Votre code</p>
                  <p style="margin:0;color:#1e3a5f;font-size:40px;font-weight:800;letter-spacing:12px;font-family:monospace;">{{ $code }}</p>
                </div>
              </div>

              <p style="margin:0 0 8px;color:#6b7280;font-size:13px;text-align:center;">
                ⏱ Ce code expire dans <strong>10 minutes</strong>
              </p>

              <hr style="border:none;border-top:1px solid #e5e7eb;margin:28px 0;" />

              <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
                Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Votre compte reste sécurisé.<br/>
                Ne partagez jamais ce code avec qui que ce soit.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
              <p style="margin:0;color:#9ca3af;font-size:11px;">
                © {{ date('Y') }} ESL — School of Health of Libreville. Tous droits réservés.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
