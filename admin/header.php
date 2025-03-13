<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NocoDB Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        .error {
            color: #b71c1c;
            background-color: #ffebee;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .success {
            color: #1b5e20;
            background-color: #e8f5e9;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .info {
            color: #0d47a1;
            background-color: #e3f2fd;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .project-card {
            text-decoration: none;
            color: inherit;
        }
        
        .project-card article {
            height: 100%;
            transition: all 0.2s ease;
        }
        
        .project-card article:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .user-info a {
            margin-left: 1rem;
        }
    </style>
</head>
<body>