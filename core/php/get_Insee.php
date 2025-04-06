<?php
header('Content-Type: application/json');

// Vérifier que le code postal est bien envoyé
if (!isset($_POST['zipCode2'])) {
    echo json_encode([]);
    exit;
}

$zipCode = trim($_POST['zipCode2']);

// Vérification basique du format du code postal
if (!preg_match('/^\d{5}$/', $zipCode)) {
    echo json_encode([]);
    exit;
}

// Construction de l'URL de l'API
$url = "https://geo.api.gouv.fr/communes?codePostal=" . urlencode($zipCode) . "&fields=nom,code&format=json";

// Appel à l'API avec file_get_contents
$response = @file_get_contents($url);

if ($response === FALSE) {
    echo json_encode([]);
    exit;
}

// Conversion JSON → tableau PHP
$communes = json_decode($response, true);

// Reformater les données pour ton <select>
$formatted = array_map(function($commune) {
    return [
        'code_insee' => $commune['code'],
        'nom_commune' => $commune['nom']
    ];
}, $communes);

// Envoi du résultat au format JSON
echo json_encode($formatted);

