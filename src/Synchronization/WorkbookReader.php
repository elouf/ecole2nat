<?php

namespace Ecole2Nat\Synchronization;

use Ecole2Nat\Support\ContactList;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Ecole2Nat\Support\GroupScheduleParser;

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
        $referenceSheets = $this->findReferenceSheets($spreadsheet);
        $competitionsSheet = $this->findSheet($spreadsheet, ['competitions', 'competition']);

        $errors = [];
        if ($groupsSheet === null) {
            $errors[] = 'Onglet Groupes ou Catégories introuvable.';
        }
        if ($registrationsSheet === null) {
            $errors[] = 'Onglet Inscriptions introuvable.';
        }
        if ($referenceSheets === []) {
            $errors[] = 'Aucun onglet Référentiel <catégorie> trouvé.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => [], 'data' => []];
        }

        $warnings = [];
        $groups = $this->readGroups($groupsSheet, $errors, $warnings);
        $reference = [];
        foreach ($referenceSheets as $referenceSheet) {
            $reference = array_merge(
                $reference,
                $this->readReference(
                    $referenceSheet['sheet'],
                    $referenceSheet['category'],
                    $errors,
                    $warnings
                )
            );
        }
        $swimmers = $this->readSwimmers($registrationsSheet, $errors, $warnings);
        $competitions = $competitionsSheet !== null ? $this->readCompetitions($competitionsSheet, $errors) : [];

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'data' => [
                'groups' => $groups,
                'reference' => $reference,
                'swimmers' => $swimmers,
                'competitions' => $competitions,
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

    private function findReferenceSheets($spreadsheet): array
    {
        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $normalized = $this->normalize($title);

            if ($normalized === 'referentiel') {
                $sheets[] = ['sheet' => $sheet, 'category' => null];
                continue;
            }

            if (str_starts_with($normalized, 'referentiel ')) {
                $category = trim((string) preg_replace('/^r[ée]f[ée]rentiel\s+/iu', '', $title));
                if ($category !== '') {
                    $sheets[] = ['sheet' => $sheet, 'category' => $category];
                }
            }
        }

        return $sheets;
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
                if ($value === null || $value === '') {
                    $value = $this->mergedCellValue($sheet, $column, $row);
                }
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

    private function mergedCellValue($sheet, int $column, int $row)
    {
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if (
                $column < $start[0]
                || $column > $end[0]
                || $row < $start[1]
                || $row > $end[1]
            ) {
                continue;
            }

            return $sheet->getCell([$start[0], $start[1]])->getValue();
        }

        return null;
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

            $schedule = GroupScheduleParser::parse($name);
            $weekday = $schedule['weekday'];
            $startTime = $schedule['start_time'];
            $durationRaw = $this->string($row, ['duree min', 'duree', 'duree en minutes']);
            $durationMinutes = null;
            if ($durationRaw !== '') {
                if (!ctype_digit($durationRaw) || (int) $durationRaw <= 0 || (int) $durationRaw > 1440) {
                    $errors[] = sprintf('Onglet Groupes, ligne %d : Durée (min) doit être un entier compris entre 1 et 1440.', $row['_row']);
                    continue;
                }
                $durationMinutes = (int) $durationRaw;
                if ($startTime === null) {
                    $errors[] = sprintf('Onglet Groupes, ligne %d : une durée nécessite une heure de début reconnue dans le nom du groupe.', $row['_row']);
                    continue;
                }
            }

            if ($weekday === null || $startTime === null) {
                $warnings[] = sprintf(
                    'Groupe « %s » : jour ou heure non reconnus dans le nom. Le groupe sera importé, mais son créneau devra être complété dans le back-office.',
                    $name
                );
            }

            $groups[$key] = compact('name', 'category', 'code', 'weekday', 'startTime', 'durationMinutes');
        }
        return array_values($groups);
    }

    private function readReference($sheet, ?string $sheetCategory, array &$errors, array &$warnings): array
    {
        $rows = [];
        foreach ($this->rows($sheet) as $row) {
            $category = $sheetCategory ?? $this->string($row, ['categorie']);
            $domain = $this->string($row, ['domaine']);
            $skill = $this->string($row, ['competences', 'competence']);
            $exerciseText = $this->string($row, ['exercices', 'exercice']);
            if ($category === '' && $domain === '' && $skill === '' && $exerciseText === '') {
                continue;
            }
            if ($category === '' || $domain === '' || $skill === '') {
                $errors[] = sprintf('Onglet %s, ligne %d : Domaine et Compétence sont obligatoires.', $sheet->getTitle(), $row['_row']);
                continue;
            }
            $exercises = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;\n]+/u', $exerciseText) ?: []))));
            $rows[] = compact('category', 'domain', 'skill', 'exercises');
        }
        return $rows;
    }

    private function readSwimmers($sheet, array &$errors, array &$warnings): array
    {
        $swimmers = [];
        $identities = [];
        foreach ($this->rows($sheet) as $row) {
            $renewal = $this->normalize($this->string($row, ['renouvellement']));
            if (!in_array($renewal, ['oui', 'non'], true)) {
                continue;
            }

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
            $email = ContactList::normalizeEmails($this->string($row, ['email', 'e-mail']));
            $phone = ContactList::normalizePhones($this->string($row, ['telephone', 'tel']));
            $healthAlert = $this->string($row, ['info médicale', 'information médicale']) !== '' ? 1 : 0;
            $competitionText = $this->string($row, ['competition', 'categorie competition', 'catégorie compétition']);
            $competitionCategories = $this->uniqueNormalizedLabels($competitionText);

            $imageRightsRaw = $this->normalize(
                $this->string($row, ["droit à l'image", 'droit image'])
            );
            $imageRights = null;
            if (in_array($imageRightsRaw, ['oui', 'o', 'yes', '1'], true)) {
                $imageRights = 1;
            } elseif (in_array($imageRightsRaw, ['non', 'n', 'no', '0'], true)) {
                $imageRights = 0;
            } elseif ($imageRightsRaw !== '') {
                $warnings[] = sprintf(
                    '%s %s : valeur de droit à l’image non reconnue « %s » (OUI ou NON attendu).',
                    $firstName,
                    $lastName,
                    $this->string($row, ["droit à l'image", 'droit image'])
                );
            }

            $groupName = trim($category . ' ' . $slot);
            $swimmers[] = [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'licence_number' => $licence,
                'responsible_email' => $email,
                'responsible_phone' => $phone,
                'health_alert' => $healthAlert,
                'competition_categories' => $competitionCategories,
                'image_rights' => $imageRights,
                'category' => $category,
                'slot' => $slot,
                'group_name' => $groupName,
                'identity' => $identity,
            ];
        }
        return $swimmers;
    }

    private function readCompetitions($sheet, array &$errors): array
    {
        $competitions = [];
        foreach ($this->rows($sheet) as $row) {
            $code = $this->string($row, ['code competition', 'code']);
            $name = $this->string($row, ['nom', 'competition']);
            if ($code === '' && $name === '') continue;
            $startDate = $this->date($row, ['date debut', 'date']);
            $endDate = $this->date($row, ['date fin']) ?? $startDate;
            $registrationStart = $this->date($row, ['debut inscriptions', 'debut inscription']);
            $registrationEnd = $this->date($row, ['fin inscriptions', 'fin inscription']);
            $categoryText = $this->string($row, ['categories de competiteurs', 'categories', 'categorie']);
            $targets = $this->uniqueNormalizedLabels($categoryText);
            if ($code === '' || $name === '' || $startDate === null || $registrationStart === null || $registrationEnd === null || $targets === []) {
                $errors[] = sprintf('Onglet Compétitions, ligne %d : Code compétition, Nom, Date début, Début inscriptions, Fin inscriptions et Catégories de compétiteurs sont obligatoires.', $row['_row']);
                continue;
            }
            if ($registrationEnd < $registrationStart || $endDate < $startDate) {
                $errors[] = sprintf('Onglet Compétitions, ligne %d : les dates de fin doivent être postérieures ou égales aux dates de début.', $row['_row']);
                continue;
            }
            $key = $this->normalize($code);
            if (isset($competitions[$key])) {
                $errors[] = sprintf('Code compétition dupliqué : %s.', $code);
                continue;
            }
            $statusRaw = $this->normalize($this->string($row, ['statut']));
            if (!in_array($statusRaw, ['', 'publiee','publie','published','brouillon','draft','annulee','annule','cancelled'], true)) {
                $errors[] = sprintf('Onglet Compétitions, ligne %d : statut « %s » non reconnu.', $row['_row'], $this->string($row, ['statut']));
                continue;
            }
            $status = match ($statusRaw) { 'brouillon', 'draft' => 'draft', 'annulee', 'annule', 'cancelled' => 'cancelled', default => 'published' };
            $programRaw=$this->string($row,['programme']);$programUrl=esc_url_raw($programRaw);
            $carpoolRaw=$this->string($row,['covoiturage']);$carpoolUrl=esc_url_raw($carpoolRaw);
            $liveffnRaw=$this->string($row,['liveffn','live ffn']);$liveffnUrl=esc_url_raw($liveffnRaw);
            $photoAlbumRaw=$this->string($row,['album photo','album photos']);$photoAlbumUrl=esc_url_raw($photoAlbumRaw);
            if(($programRaw!==''&&$programUrl==='')||($carpoolRaw!==''&&$carpoolUrl==='')||($liveffnRaw!==''&&$liveffnUrl==='')||($photoAlbumRaw!==''&&$photoAlbumUrl==='')){$errors[]=sprintf('Onglet Compétitions, ligne %d : le lien Programme, Covoiturage, liveFFN ou Album photo n’est pas une URL valide.',$row['_row']);continue;}
            $normalizedTargets=array_map([$this,'normalize'],$targets);$all=in_array('tous',$normalizedTargets,true);$competitionCategories=[];
            foreach($targets as $target)if($this->normalize($target)!=='tous')$competitionCategories[]=$target;
            if($all && count($targets)>1){$errors[]=sprintf('Onglet Compétitions, ligne %d : TOUS ne peut pas être combiné avec une catégorie de compétiteur.',$row['_row']);continue;}
            $competitions[$key] = [
                'code'=>$code, 'name'=>$name, 'start_date'=>$startDate, 'end_date'=>$endDate,
                'location'=>$this->string($row, ['lieu']),
                'registration_opens_at'=>$registrationStart . ' 00:00:00',
                'registration_closes_at'=>$registrationEnd . ' 23:59:59',
                'competition_categories'=>$competitionCategories, 'target_all'=>$all?1:0,
                'technical_document_url'=>esc_url_raw($this->string($row, ['fiche technique', 'pdf'])),
                'program_url'=>$programUrl,
                'carpool_url'=>$carpoolUrl, 'liveffn_url'=>$liveffnUrl,
                'photo_album_url'=>$photoAlbumUrl,
                'information'=>$this->string($row, ['informations', 'information']),
                'status'=>$status,
            ];
        }
        return array_values($competitions);
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

    private function uniqueNormalizedLabels(string $value): array
    {
        $labels = [];
        foreach (preg_split('/[\/;\n]+/u', $value) ?: [] as $label) {
            $label = trim($label);
            $key = $this->normalize($label);
            if ($key !== '' && !isset($labels[$key])) {
                $labels[$key] = $label;
            }
        }
        return array_values($labels);
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
