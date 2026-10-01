<?php

class ActiveUser
{
    public function renderDashboard(): void
    {
        $id = $_GET['id'] ?? 1;
        // Raw SQL smell
        $query = "SELECT * FROM users WHERE id = " . $id;
        echo "Rendering user dashboard for: " . $id;
    }

    public function abandonedMethodOnly(): void
    {
        // This method has zero call sites!
        echo "I am never invoked anywhere!";
    }
}
