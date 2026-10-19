<?php

namespace Ecole2Nat\Competition;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!defined('ABSPATH')) { exit; }

final class CompetitionParticipantExportService
{
    public function createWorkbook(array $competition, array $participants): Spreadsheet
    {
        $workbook = new Spreadsheet();
        $workbook->getProperties()
            ->setCreator("Ecole2Nat'")
            ->setTitle(sprintf('Participants — %s', (string) ($competition['name'] ?? 'Compétition')));

        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Participants');
        $headers = ['Nom', 'Prénom', 'Catégorie', 'Genre', 'Statut'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
        }

        $row = 2;
        foreach ($participants as $participant) {
            $values = [
                (string) ($participant['last_name'] ?? ''),
                (string) ($participant['first_name'] ?? ''),
                (string) ($participant['category_name'] ?? ''),
                $this->genderLabel((string) ($participant['gender'] ?? '')),
                $this->statusLabel((string) ($participant['participant_status'] ?? '')),
            ];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit([$index + 1, $row], $value, DataType::TYPE_STRING);
            }
            $statusColor = match ((string) ($participant['participant_status'] ?? '')) {
                'registered' => 'F4B183',
                'validated' => 'A9D18E',
                default => null,
            };
            if ($statusColor !== null) {
                $sheet->getStyle("E{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
            $row++;
        }

        $lastRow = max(1, $row - 1);
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2271B1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->setAutoFilter("A1:E{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->setSelectedCell('A1');

        return $workbook;
    }

    public function download(array $competition, array $participants): void
    {
        $filename = sanitize_file_name(sprintf(
            'participants-%s-%s.xlsx',
            (string) ($competition['name'] ?? 'competition'),
            (string) ($competition['start_date'] ?? wp_date('Y-m-d'))
        ));

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        (new Xlsx($this->createWorkbook($competition, $participants)))->save('php://output');
    }

    private function genderLabel(string $gender): string
    {
        return match (strtoupper(trim($gender))) {
            'F' => 'Femme',
            'M' => 'Homme',
            default => '',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'registered' => 'Inscrit',
            'validated' => 'Validé',
            default => '',
        };
    }
}
