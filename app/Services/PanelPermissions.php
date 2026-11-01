<?php

namespace App\Services;

class PanelPermissions
{
    public const PAGES = ['dashboard' => 'events.view', 'kegiatan' => 'events.view', 'materi' => 'events.view', 'peserta' => 'participants.view', 'absensi' => 'attendance.view', 'pretest' => 'assessments.pretest.view', 'posttest' => 'assessments.posttest.view', 'keaktifan' => 'assessments.activity.view', 'etika' => 'assessments.ethics.view', 'pemahaman' => 'assessments.understanding.view', 'perhitungan' => 'results.view', 'hasil' => 'results.view', 'kriteria' => 'criteria.view'];

    public const INPUTS = ['absensi' => 'attendance.input', 'pretest' => 'assessments.pretest.input', 'posttest' => 'assessments.posttest.input', 'keaktifan' => 'assessments.activity.input', 'etika' => 'assessments.ethics.input', 'pemahaman' => 'assessments.understanding.input'];

    /** Kriteria & Bobot punya izin sendiri supaya bisa dibaca tanpa boleh diubah. */
    public const WEIGHTS_VIEW = 'criteria.view';

    public const WEIGHTS_MANAGE = 'criteria.manage';

    /** Halaman Log Aktivitas: pembacaan jejak audit, dipisah agar bisa diberikan ke ketua. */
    public const ACTIVITY_VIEW = 'activity.view';

    public const ALL = ['events.view', 'events.manage', 'participants.view', 'participants.manage', 'materials.manage', 'attendance.view', 'attendance.input', 'assessments.pretest.view', 'assessments.pretest.input', 'assessments.activity.view', 'assessments.activity.input', 'assessments.ethics.view', 'assessments.ethics.input', 'assessments.understanding.view', 'assessments.understanding.input', 'assessments.posttest.view', 'assessments.posttest.input', 'results.view', 'results.calculate', 'results.finalize', 'results.unlock', self::WEIGHTS_VIEW, self::WEIGHTS_MANAGE, self::ACTIVITY_VIEW];
}
