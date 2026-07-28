<?php
/**
 * Generate Excel Template
 * 
 * This script creates a proper Excel template for B2B import.
 * Run this AFTER installing composer dependencies.
 * 
 * Usage: php generate-template.php
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line.');
}

// Check if vendor/autoload.php exists
if (!file_exists(__DIR__ . '/includes/vendor/autoload.php')) {
    die("Error: Dependencies not installed.\nPlease run: composer install --no-dev\n");
}

require __DIR__ . '/includes/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

echo "📝 Generating Excel template...\n";

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('B2B Import');

// Define headers
$headers = [
    'A1' => 'OIB',
    'B1' => 'Šifra partnera',
    'C1' => 'Naziv tvrtke',
    'D1' => 'Korisničko ime',
    'E1' => 'Ime',
    'F1' => 'Prezime',
    'G1' => 'Email',
    'H1' => 'Mobitel',
    'I1' => 'Ulica',
    'J1' => 'Kućni broj',
    'K1' => 'Mjesto',
    'L1' => 'Poštanski broj',
    'M1' => 'Email komercijalista'
];

// Set headers
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Style header row
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2271B1']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Set column widths
$columnWidths = [
    'A' => 15,  // OIB
    'B' => 15,  // Šifra partnera
    'C' => 30,  // Naziv tvrtke
    'D' => 20,  // Korisničko ime
    'E' => 15,  // Ime
    'F' => 15,  // Prezime
    'G' => 30,  // Email
    'H' => 18,  // Mobitel
    'I' => 25,  // Ulica
    'J' => 12,  // Kućni broj
    'K' => 20,  // Mjesto
    'L' => 12,  // Poštanski broj
    'M' => 30   // Email komercijalista
];

foreach ($columnWidths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

// Add example data (2 rows)
$exampleData = [
    ['12345678901', 'TASK001', 'Primjer d.o.o.', 'ivan.horvat', 'Ivan', 'Horvat', 'ivan.horvat@example.com', '+385 91 123 4567', 'Ilica', '123', 'Zagreb', '10000', 'komercijalist@firma.hr'],
    ['23456789012', 'TASK002', 'Test Company d.o.o.', '', 'Marko', 'Marić', 'marko.maric@test.com', '+385 92 234 5678', 'Vukovarska', '45', 'Split', '21000', 'komercijalist2@firma.hr']
];

$row = 2;
foreach ($exampleData as $rowData) {
    $col = 'A';
    foreach ($rowData as $value) {
        $sheet->setCellValue($col . $row, $value);
        $col++;
    }
    
    // Style data rows
    $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $row % 2 == 0 ? 'F9F9F9' : 'FFFFFF']
        ]
    ]);
    
    $row++;
}

// Add instructions sheet
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Upute');

$instructions = [
    ['📋 UPUTE ZA POPUNJAVANJE DATOTEKE'],
    [''],
    ['Molimo pridržavajte se sljedećih pravila:'],
    [''],
    ['1. OBAVEZNA POLJA:', 'OIB, Naziv tvrtke, Ime, Prezime, Email'],
    ['2. Korisničko ime:', 'Ako ostavite prazno, automatski će se generirati iz emaila'],
    ['3. Email format:', 'Mora biti validan email (npr. ime@domena.hr)'],
    ['4. Email komercijalista:', 'Mora biti identičan emailu u WordPress CPT "Komercijalist"'],
    ['5. Prvi red (zaglavlje):', 'NE MIJENJAJTE! To su nazivi kolona.'],
    ['6. Brisanje primjera:', 'Slobodno obrišite redove 2 i 3 (primjeri) i dodajte svoje podatke'],
    [''],
    ['⚠️ VAŽNO:'],
    ['- NE mijenjajte redoslijed kolona'],
    ['- NE dodavajte dodatne kolone'],
    ['- Svaki red mora imati sve kolone'],
    ['- Email adrese moraju biti unikatne'],
    [''],
    ['🎯 SAVJETI:'],
    ['- Kopirajte podatke iz Task izvoza direktno'],
    ['- Provjerite sve emailove prije importa'],
    ['- Koristite "Test mod" za prvu provjeru'],
    ['- Spremite kopiju datoteke prije uploada'],
    [''],
    ['✅ Nakon popunjavanja:'],
    ['1. Spremite datoteku (File → Save)'],
    ['2. Idi na WordPress Admin → Tools → Import B2B Partnera'],
    ['3. Uploadajte datoteku'],
    ['4. Označite "Test mod" za prvu provjeru'],
    ['5. Klikni "Započni Import"'],
    [''],
    ['📧 Support: team@uncledev.info']
];

$instructionsSheet->setCellValue('A1', $instructions[0][0]);
$instructionsSheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '2271B1']
    ]
]);

$row = 2;
foreach (array_slice($instructions, 1) as $instruction) {
    $col = 'A';
    foreach ($instruction as $value) {
        $instructionsSheet->setCellValue($col . $row, $value);
        $col++;
    }
    $row++;
}

$instructionsSheet->getColumnDimension('A')->setWidth(30);
$instructionsSheet->getColumnDimension('B')->setWidth(50);

// Save file
$outputPath = __DIR__ . '/template/b2b-import-template.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($outputPath);

echo "✅ Template created successfully!\n";
echo "📁 Location: " . $outputPath . "\n";
echo "\n";
echo "🎉 You can now use this template for importing B2B partners.\n";
