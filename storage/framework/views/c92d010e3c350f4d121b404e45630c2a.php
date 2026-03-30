<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Academic Sheet</title>
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
      @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
  </head>
  <body>
    <?php
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
    ?>

    <div class="sheet">
      <div class="header">
        <div class="uni">
          <?php if($logoDataUri): ?>
            <img class="logo" src="<?php echo e($logoDataUri); ?>" alt="University Logo" />
          <?php endif; ?>
          <div>
            <div class="uni-name"><?php echo e($universityName); ?></div>
            <div class="doc-title">Academic Sheet (Curriculum)</div>
          </div>
        </div>
        <div class="meta">
          <div><strong>Student:</strong> <?php echo e($student['full_name'] ?? 'N/A'); ?></div>
          <div><strong>Student ID:</strong> <?php echo e($student['student_id'] ?? 'N/A'); ?></div>
          <div><strong>Department:</strong> <?php echo e($student['department'] ?? 'N/A'); ?></div>
          <div><strong>Level:</strong> <?php echo e($student['level'] ?? 'N/A'); ?> <?php if(!empty($student['current_semester'])): ?> (Sem <?php echo e($student['current_semester']); ?>) <?php endif; ?></div>
          <div><strong>Generated:</strong> <?php echo e($payload['generated_at'] ?? ''); ?></div>
        </div>
      </div>

      <div class="section-title">All programme courses (including not taken)</div>

      <?php $__currentLoopData = $grouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level => $semesters): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="margin-top: 12px;">
          <div style="font-weight: 800; margin-bottom: 6px;"><?php echo e($level); ?></div>
          <?php $__currentLoopData = $semesters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sem => $courses): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div style="margin: 10px 0;">
              <div style="font-weight: 700; margin-bottom: 6px;">Semester <?php echo e($sem); ?></div>
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
                  <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                      <td style="font-family: monospace;"><?php echo e($c['code'] ?? ''); ?></td>
                      <td><?php echo e($c['name'] ?? ''); ?></td>
                      <td><?php echo e($c['credits'] ?? ''); ?></td>
                      <td>
                        <?php $st = $c['status'] ?? 'not_taken'; ?>
                        <span class="pill">
                          <?php if($st === 'passed'): ?> Passed
                          <?php elseif($st === 'failed'): ?> Failed
                          <?php elseif($st === 'in_progress'): ?> In progress
                          <?php else: ?> Not taken
                          <?php endif; ?>
                        </span>
                      </td>
                      <td>
                        <?php if(!empty($c['grade'])): ?>
                          <?php echo e($c['grade']['final_grade'] ?? ''); ?>/100
                          <?php if(!empty($c['grade']['letter_grade'])): ?>
                            (<?php echo e($c['grade']['letter_grade']); ?>)
                          <?php endif; ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </body>
</html>

<?php /**PATH /Users/macbookpro/Documents/Unilak/FYP/ESL2/backend/resources/views/reports/student_academic_sheet.blade.php ENDPATH**/ ?>