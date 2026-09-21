<?php
// Header común: abre <html>, <head> y muestra el nav principal
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/Progel_cores/assets/style.css" rel="stylesheet">
    <title>PROGEL CORE</title>
    <style>
        .navbar-progel { background-color: #0b3d91; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .brand-logo { height: 38px; width: auto; margin-right: 12px; object-fit: contain; }
        .user-pill { font-size: 0.85rem; color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); padding: 0.4rem 1rem; border-radius: 50px; letter-spacing: 0.5px; }
        .equipo-card-base { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; padding: 1rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important; border-radius: .25rem; text-decoration: none; transition: transform 0.2s ease, box-shadow 0.2s ease; min-height: 120px; background-color: #fff !important; color: #212529 !important; border-width: 2px !important; border-style: solid !important; }
        .equipo-card-base:hover { transform: translateY(-4px); box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12) !important; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark navbar-progel shadow-sm py-2">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center" href="/Progel_cores/index.php">
            <img src="/Progel_cores/img/blanco.png" alt="Logo" class="brand-logo">
            <div class="d-flex flex-column justify-content-center"><span class="h5 mb-0 fw-bold" style="letter-spacing: 1px;">PROGEL <span class="fw-normal">CORE</span></span></div>
        </a>
        <div class="d-flex align-items-center"><span class="user-pill">Operador</span></div>
    </div>
</nav>
<main class="container mt-4">
