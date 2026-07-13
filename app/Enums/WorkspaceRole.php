<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkspaceRole: string
{
    case Admin = "admin";
    case Editor = "editor";
    case Viewer = "viewer";

    public function label(): string
    {
        return match ($this) {
            self::Admin => "Administrador",
            self::Editor => "Editor",
            self::Viewer => "Visualizador",
        };
    }
}
