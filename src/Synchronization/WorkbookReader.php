<?php

namespace Ecole2Nat\Synchronization;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if (!defined('ABSPATH')) {
    exit;
}

final class WorkbookReader
{
    public function read(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $groupsSheet = $this->findSheet($spreadsheet, ['groupes', 'catégories', 'categories']);
        $registrationsSheet = $this->findSheet($spreadsheet, ['inscriptions', 'inscription']);
        $referenceSheet = $this->findSheet($spreadsheet, ['référentiel', 'referentiel']);

        $errors = [];
        if ($groupsSheet === null) {
            $errors[] = 'Onglet Groupes ou Catégories introuvable.';
        }
        if ($registrationsSheet === null) {
            $errors[] = 'Onglet Inscriptions introuvable.';
        }
        if ($referenceSheet === null) {
            $errors[] = 'Onglet Référentiel introuvable.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => [], 'data' => []];
        }

        $warnings = [];
        $groups = $this->readGroups($groupsSheet, $errors, $warnings);
        $reference = $this->readReference($referenceSheet, $errors, $warnings);
        $swimmers = $this->readSwimmers($registrationsSheet, $errors, $warnings);

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'data' => [
                'groups' => $groups,
                'reference' => $reference,
                'swimmers' => $swimmers,
            ],
        ];
    }

    private function findSheet($spreadsheet, array $names)
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $normalized = $this->normalize($sheet->getTitle());
            foreach ($names as $name) {
                if ($normalized === $this->normalize($name)) {
                    return $sheet;
                }
            }
        }
        return null;
    }

    private function rows($sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = $sheet->getHighestDataRow();
        $headers = [];
        for ($column = 1; $column <= $highestColumn; $column++) {
            $value = trim((string) $sheet->getCell([$column, 1])->getFormattedValue());
            if ($value !== '') {
                $headers[$column] = $this->normalize($value);
            }
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $item = [];
            $hasValue = false;
            foreach ($headers as $column => $header) {
                $cell = $sheet->getCell([$column, $row]);
                $value = $cell->getValue();
                if ($value !== null && $value !== '') {
                    $hasValue = true;
                }
                $item[$header] = $value;
            }
            if ($hasValue) {
                $item['_row'] = $row;
                $rows[] = $item;
            }
        }
        return $rows;
    }

    private function readGroups($sheet, array &$errors, array &$warnings): array
    {
        $groups = [];
        foreach ($this->rows($sheet) as $row) {
            $name = $this->string($row, ['groupe', 'nom']);
            $category = $this->string($row, ['categorie']);
            $code = $this->string($row, ['code']);
            if ($name === '' && $category === '') {
                continue;
            }
            if ($name === '' || $category === '') {
                $errors[] = sprintf('Onglet Groupes, ligne %d : Groupe et Catégorie sont obligatoires.', $row['_row']);
                continue;
            }
            $key = $this->key($category, $name);
            if (isset($groups[$key])) {
                $errors[] = sprintf('Groupe dupliqué : %s (%s).', $name, $category);
                continue;
            }
            $groups[$key] = compact('name', 'category', 'code');
        }
        return array_values($groups);
    }

    private function readReference($sheet, array &$errors, array &$warnings): array
    {
        $rows = [];
        foreach ($this->rows($sheet) as $row) {
            $category = $this->string($row, ['categorie']);
            $domain = $this->string($row, ['domaine']);
            $domainCode = $this->string($row, ['code domaine']);
            $skill = $this->string($row, ['competences', 'competence']);
            $skillCode = $this->string($row, ['code competence']);
            $exerciseText = $this->string($row, ['exercices', 'exercice']);
            if ($category === '' && $domain === '' && $skill === '' && $exerciseText === '') {
                continue;
            }
            if ($category === '' || $domain === '' || $skill === '') {
                $errors[] = sprintf('Onglet Référentiel, ligne %d : Catégorie, Domaine et Compétence sont obligatoires.', $row['_row']);
                continue;
            }
            $exercises = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;\n]+/u', $exerciseText) ?: []))));
            $rows[] = compact('category', 'domain', 'domainCode', 'skill', 'skillCode', 'exercises');
        }
        return $rows;
    }

    private function readSwimmers($sheet, array &$errors, array &$warnings): array
    {
        $swimmers = [];
        $identities = [];
        foreach ($this->rows($sheet) as $row) {
            $lastName = $this->string($row, ['nom']);
            $firstName = $this->string($row, ['prenom']);
            if ($lastName === '' && $firstName === '') {
                continue;
            }
            $birthDate = $this->date($row, ['date de naissance']);
            $licence = $this->string($row, ['n° licence', 'no licence', 'numero licence']);
            $category = $this->string($row, ['categorie']);
            $slot = $this->string($row, ['creneau 1', 'creneau']);
            if ($lastName === '' || $firstName === '') {
                $errors[] = sprintf('Onglet Inscriptions, ligne %d : Nom et Prénom sont obligatoires.', $row['_row']);
                continue;
            }
            if ($birthDate === null) {
                $warnings[] = sprintf('%s %s : date de naissance absente ou invalide.', $firstName, $lastName);
            }
            $identity = $licence !== '' ? 'licence|' . $this->normalize($licence) : $this->key($lastName, $firstName, (string) $birthDate);
            if (isset($identities[$identity])) {
                $errors[] = sprintf('Nageur dupliqué dans le classeur : %s %s.', $firstName, $lastName);
                continue;
            }
            $identities[$identity] = true;
            $genderValue = $this->normalize($this->string($row, ['genre', 'sexe']));
            $gender = in_array($genderValue, ['femme', 'feminin', 'f'], true) ? 'F' : (in_array($genderValue, ['homme', 'masculin', 'm'], true) ? 'M' : '');
            $email = sanitize_email($this->string($row, ['email', 'e-mail']));
            $phone = $this->string($row, ['telephone', 'tel']);
            $comment = $this->string($row, ['commentaire']);
            $groupName = trim($category . ' ' . $slot);
            $swimmers[] = [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'licence_number' => $licence,
                'responsible_email' => $email,
                'responsible_phone' => $phone,
                'medical_note' => $comment,
                'category' => $category,
                'slot' => $slot,
                'group_name' => $groupName,
                'identity' => $identity,
            ];
        }
        return $swimmers;
    }

    private function string(array $row, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalize($alias);
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private function date(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalize($alias);
            if (!array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === null) {
                continue;
            }
            $value = $row[$key];
            try {
                if (is_numeric($value)) {
                    return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }
                foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
                    $date = \DateTimeImmutable::createFromFormat('!' . $format, trim((string) $value));
                    if ($date instanceof \DateTimeImmutable) {
                        return $date->format('Y-m-d');
                    }
                }
            } catch (\Throwable $exception) {
                return null;
            }
        }
        return null;
    }

    private function key(string ...$parts): string
    {
        return implode('|', array_map([$this, 'normalize'], $parts));
    }

    private function normalize(string $value): string
    {
        $value = remove_accents(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
