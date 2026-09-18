<?php
/**
 * Hospital Management System (HMS)
 * Native PDF Generator for Official Credentials Documents
 * Standards-compliant PDF 1.4 with Flate compression
 */

class PdfCredentials {
    public static function generate(array $creds, ?string $outputPath = null): string {
        $w = 595.28;
        $h = 841.89;

        $esc = function($text) {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$text);
        };

        $commands = [];

        // Background border
        $commands[] = '0.92 0.94 0.96 rg';
        $commands[] = '20 20 555.28 801.89 re f';
        $commands[] = '1 1 1 rg';
        $commands[] = '22 22 551.28 797.89 re f';

        // Header Banner (Navy)
        $commands[] = '0.06 0.18 0.28 rg';
        $commands[] = '22 755 551.28 65 re f';

        // Accent Line (Teal)
        $commands[] = '0.08 0.70 0.58 rg';
        $commands[] = '22 752 551.28 3 re f';

        // Header Text
        $commands[] = 'BT';
        $commands[] = '/F2 15 Tf';
        $commands[] = '1 1 1 rg';
        $commands[] = '35 795 Td';
        $commands[] = '(HAYAT MEDICARE HOSPITAL & RESEARCH CENTER) Tj';
        $commands[] = 'ET';

        $commands[] = 'BT';
        $commands[] = '/F1 9 Tf';
        $commands[] = '0.30 0.85 0.75 rg';
        $commands[] = '35 780 Td';
        $commands[] = '(EXECUTIVE HEALTH SYSTEM - CONFIDENTIAL ACCESS CONTROL DIRECTORY) Tj';
        $commands[] = 'ET';

        $commands[] = 'BT';
        $commands[] = '/F1 8 Tf';
        $commands[] = '0.80 0.85 0.90 rg';
        $commands[] = '35 765 Td';
        $commands[] = '(CLASSIFICATION: RESTRICTED // PHI SECURITY GOVERNANCE // ISO 27001 COMPLIANT) Tj';
        $commands[] = 'ET';

        // Security Notice Box
        $commands[] = '0.98 0.95 0.90 rg';
        $commands[] = '30 715 535 28 re f';
        $commands[] = '0.90 0.70 0.30 RG 0.75 w';
        $commands[] = '30 715 535 28 re S';

        $commands[] = 'BT';
        $commands[] = '/F2 8 Tf';
        $commands[] = '0.60 0.35 0.05 rg';
        $commands[] = '38 730 Td';
        $commands[] = '(SECURITY NOTICE: Passwords shown below are personalized name-based random keys. Do NOT share externally.) Tj';
        $commands[] = 'ET';

        $commands[] = 'BT';
        $commands[] = '/F1 7.5 Tf';
        $commands[] = '0.50 0.40 0.20 rg';
        $commands[] = '38 720 Td';
        $commands[] = '(All logins are audited with timestamp, workstation IP, and user session token in accordance with Hospital Policy.) Tj';
        $commands[] = 'ET';

        // Table Header
        $commands[] = '0.15 0.22 0.30 rg';
        $commands[] = '30 685 535 20 re f';

        $commands[] = 'BT /F2 8 Tf 1 1 1 rg 35 692 Td (ID) Tj ET';
        $commands[] = 'BT /F2 8 Tf 1 1 1 rg 55 692 Td (DEPARTMENT / OFFICER NAME) Tj ET';
        $commands[] = 'BT /F2 8 Tf 1 1 1 rg 215 692 Td (SYSTEM USERNAME) Tj ET';
        $commands[] = 'BT /F2 8 Tf 1 1 1 rg 320 692 Td (SECRET PASSWORD) Tj ET';
        $commands[] = 'BT /F2 8 Tf 1 1 1 rg 425 692 Td (ASSIGNED WORKSPACE) Tj ET';

        // Table Rows
        $y = 665;
        $rowHeight = 27;

        $workspaceMap = [
            'superadmin' => 'Executive Dashboard / Root',
            'admin.hashim' => 'Operations & Hospital Admin',
            'dr.asif' => 'Clinical Governance & MS',
            'lab.usman' => 'Clinical Pathology & Lab',
            'pharm.kamran' => 'Pharmacy & Drug Dispensary',
            'radio.farooq' => 'Radiology Modalities & PACS',
            'nurse.fatima' => 'Wards, Rooms & IPD Bed Rack',
            'dr.imran' => 'Operation Theatre Suites & Surgery',
            'dr.tariq' => 'Clinician Workbench & OPD',
            'dr.noman' => 'Emergency Triage & Trauma ER',
            'dr.sumaira' => 'Blood Bank & Transfusion Unit',
            'cashier.hassan' => 'Central Billing Desk & Cashier',
            'reception.sana' => 'Front Desk & Token Scheduler',
            'store.zubair' => 'Central Stores & Procurement'
        ];

        foreach ($creds as $i => $c) {
            if ($i % 2 === 0) {
                $commands[] = '0.96 0.97 0.98 rg';
            } else {
                $commands[] = '1 1 1 rg';
            }
            $commands[] = "30 $y 535 $rowHeight re f";

            $commands[] = "0.88 0.90 0.92 RG 0.5 w";
            $commands[] = "30 $y m 565 $y l S";

            // ID
            $idY = $y + 11;
            $idVal = $i + 1;
            $commands[] = "BT /F1 7.5 Tf 0.4 0.45 0.5 rg 35 $idY Td ($idVal) Tj ET";

            // Officer Name & Designation
            $nameY = $y + 14;
            $nameStr = $esc($c['full_name']);
            $commands[] = "BT /F2 8.5 Tf 0.1 0.15 0.2 rg 55 $nameY Td ($nameStr) Tj ET";

            $desigY = $y + 4;
            $desigStr = $esc($c['designation']);
            $commands[] = "BT /F1 7 Tf 0.45 0.5 0.55 rg 55 $desigY Td ($desigStr) Tj ET";

            // Username
            $userY = $y + 9;
            $userStr = '@' . $esc($c['username']);
            $commands[] = "BT /F3 8.5 Tf 0.05 0.45 0.40 rg 215 $userY Td ($userStr) Tj ET";

            // Password Badge Box
            $boxY = $y + 5;
            $commands[] = "0.90 0.93 0.97 rg 315 $boxY 95 16 re f";
            $commands[] = "0.75 0.80 0.90 RG 0.5 w 315 $boxY 95 16 re S";

            // Password
            $pwdY = $y + 9;
            $pwdStr = $esc($c['password']);
            $commands[] = "BT /F4 9 Tf 0.10 0.25 0.65 rg 322 $pwdY Td ($pwdStr) Tj ET";

            // Workspace
            $ws = $workspaceMap[$c['username']] ?? 'General Workspace';
            $wsY = $y + 9;
            $wsStr = $esc($ws);
            $commands[] = "BT /F1 7.5 Tf 0.25 0.3 0.35 rg 425 $wsY Td ($wsStr) Tj ET";

            $y -= $rowHeight;
        }

        // Table Outline
        $tableBottom = $y + $rowHeight;
        $tableHeight = (685 - $tableBottom) + 20;
        $commands[] = "0.75 0.80 0.85 RG 0.75 w 30 $tableBottom 535 $tableHeight re S";

        // Signature & Verification Box
        $sigY = $y - 10;
        $boxTop = $sigY - 45;
        $commands[] = "0.97 0.98 0.99 rg 30 $boxTop 535 48 re f";
        $commands[] = "0.85 0.88 0.92 RG 0.5 w 30 $boxTop 535 48 re S";

        $s1 = $sigY - 12;
        $s2 = $sigY - 24;
        $s3 = $sigY - 36;
        $commands[] = "BT /F2 7.5 Tf 0.2 0.25 0.3 rg 40 $s1 Td (ISSUING AUTHORITY & SYSTEM AUDITOR:) Tj ET";
        $commands[] = "BT /F1 7 Tf 0.4 0.45 0.5 rg 40 $s2 Td (Chief Information Officer (CIO) / IT Security Directorate) Tj ET";
        $commands[] = "BT /F1 7 Tf 0.4 0.45 0.5 rg 40 $s3 Td (Hayat Medicare Hospital Information System (HMHIS) v2.6) Tj ET";

        $lineY = $sigY - 30;
        $commands[] = "320 $lineY m 430 $lineY l S";
        $commands[] = "450 $lineY m 550 $lineY l S";

        $labelY = $sigY - 40;
        $commands[] = "BT /F1 6.5 Tf 0.5 0.5 0.5 rg 335 $labelY Td (Medical Director Signature) Tj ET";
        $commands[] = "BT /F1 6.5 Tf 0.5 0.5 0.5 rg 465 $labelY Td (Hospital Official Seal) Tj ET";

        // Footer
        $commands[] = 'BT /F1 7 Tf 0.55 0.60 0.65 rg 30 28 Td (CONFIDENTIAL - FOR INTERNAL RECIPIENT USE ONLY - UNAUTHORIZED DUPLICATION IS PROHIBITED BY LAW) Tj ET';
        $commands[] = 'BT /F1 7 Tf 0.55 0.60 0.65 rg 470 28 Td (DOC ID: HMH-MGT-2026 // P. 1/1) Tj ET';

        $content = implode("\n", $commands);
        $compressed = gzcompress($content);

        $objs = [];
        $objs[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objs[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 8 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R /F4 7 0 R >> >> >>', $w, $h);
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>';
        $objs[] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($compressed), $compressed);

        $pdf = ["%PDF-1.4\n%\xe2\xe3\xcf\xd3\n"];
        $offsets = [];
        $currentOffset = strlen($pdf[0]);

        foreach ($objs as $i => $obj) {
            $offsets[] = $currentOffset;
            $objStr = sprintf("%d 0 obj\n%s\nendobj\n", $i + 1, $obj);
            $pdf[] = $objStr;
            $currentOffset += strlen($objStr);
        }

        $xrefOffset = $currentOffset;
        $pdf[] = sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objs) + 1);
        foreach ($offsets as $off) {
            $pdf[] = sprintf("%010d 00000 n \n", $off);
        }

        $pdf[] = sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objs) + 1, $xrefOffset);

        $fullPdf = implode('', $pdf);

        if ($outputPath) {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($outputPath, $fullPdf);
        }

        return $fullPdf;
    }
}
