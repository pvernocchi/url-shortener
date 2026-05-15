<?php
declare(strict_types=1);

namespace App\Core;

class Migration
{
    /**
     * Run pending migrations and return list of executed filenames.
     */
    public function run(Database $db, string $migrationsPath): array
    {
        $prefix = Config::get('db.prefix', 'us_');

        // Ensure migrations table exists
        $db->query("
            CREATE TABLE IF NOT EXISTS `{$prefix}migrations` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `filename` VARCHAR(255) NOT NULL UNIQUE,
              `ran_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Get already-run migrations
        $ran = $db->fetchAll("SELECT `filename` FROM `{$prefix}migrations`");
        $ranFiles = array_column($ran, 'filename');

        // Find all .sql files
        $files = glob($migrationsPath . '/*.sql');
        if (!$files) {
            return [];
        }
        sort($files);

        $executed = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $ranFiles, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration file: $file");
            }

            // Replace prefix placeholder
            $sql = str_replace('{prefix}', $prefix, $sql);

            // Split on semicolons (but not inside strings – simple split is fine for our SQL)
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => $s !== ''
            );

            foreach ($statements as $statement) {
                $db->query($statement);
            }

            $db->insert("{$prefix}migrations", [
                'filename' => $filename,
                'ran_at'   => date('Y-m-d H:i:s'),
            ]);

            $executed[] = $filename;
        }

        return $executed;
    }
}
