<?php
/**
 * mysqli_stmt helpers for hosts built without mysqlnd (mysqli_stmt::get_result() missing).
 */

if (!class_exists('OlfuStmtEmulatedResult', false)) {
    /**
     * Minimal mysqli_result-like wrapper using bind_result() for statements without mysqlnd.
     */
    final class OlfuStmtEmulatedResult
    {
        /** @var mysqli_stmt */
        private $stmt;
        /** Whether store_result() succeeded (roughly: this execution produced a buffered result). */
        public $ok = false;
        /** Whether columns were bound (false for INSERT/UPDATE-style statements with no result set). */
        public $bound = false;
        /** @var array<string,mixed> */
        private $row = [];
        /** @var list<mixed> */
        private $refs = [];

        /** @var int */
        public $num_rows = 0;

        public function __construct(mysqli_stmt $stmt)
        {
            $this->stmt = $stmt;
            if (!@$stmt->store_result()) {
                return;
            }
            $this->ok = true;
            $this->num_rows = (int) $stmt->num_rows;
            $meta = $stmt->result_metadata();
            if (!$meta) {
                return;
            }
            while ($field = $meta->fetch_field()) {
                $this->row[$field->name] = null;
                $this->refs[] = &$this->row[$field->name];
            }
            $meta->free();
            if ($this->refs !== [] && @call_user_func_array([$stmt, 'bind_result'], $this->refs)) {
                $this->bound = true;
            }
        }

        /**
         * @return array<string,mixed>|null
         */
        public function fetch_assoc()
        {
            if (!$this->bound) {
                return null;
            }
            if (!$this->stmt->fetch()) {
                return null;
            }
            $out = [];
            foreach ($this->row as $k => $v) {
                $out[$k] = $v;
            }

            return $out;
        }

        /**
         * @param int $mode MYSQLI_ASSOC etc.
         * @return list<array<string,mixed>>
         */
        public function fetch_all($mode = MYSQLI_ASSOC)
        {
            if ((int) $mode !== MYSQLI_ASSOC && (int) $mode !== 3) {
                $rows = [];
                while (($r = $this->fetch_row()) !== null) {
                    $rows[] = $r;
                }

                return $rows;
            }
            $rows = [];
            while (($r = $this->fetch_assoc()) !== null) {
                $rows[] = $r;
            }

            return $rows;
        }

        /**
         * @return list<mixed>|null
         */
        public function fetch_row()
        {
            $r = $this->fetch_assoc();
            if ($r === null) {
                return null;
            }

            return array_values($r);
        }

        public function free()
        {
            @$this->stmt->free_result();
        }

        public function free_result()
        {
            $this->free();
        }

        public function close()
        {
            $this->free();
        }
    }
}

if (!function_exists('olfu_stmt_get_result')) {
    /**
     * Drop-in replacement for mysqli_stmt::get_result() when mysqlnd is unavailable.
     *
     * @return mysqli_result|OlfuStmtEmulatedResult|false
     */
    function olfu_stmt_get_result(mysqli_stmt $stmt)
    {
        if (method_exists($stmt, 'get_result')) {
            $r = @$stmt->get_result();
            if ($r instanceof mysqli_result) {
                return $r;
            }
        }

        $emu = new OlfuStmtEmulatedResult($stmt);
        if (!$emu->ok || !$emu->bound) {
            return false;
        }

        return $emu;
    }
}

if (!function_exists('mysqli_stmt_bind_param_safe')) {
    /**
     * Dynamic bind_param for any number of placeholders.
     * mysqli_stmt::bind_param() requires variables passed by reference; using ...$array
     * breaks on PHP before 8.1 and fatals ("cannot pass parameter by reference").
     * Uses named variables + references — reliable across PHP 7.x–8.x (refs-to-array-elements can fail).
     */
    function mysqli_stmt_bind_param_safe(mysqli_stmt $stmt, string $types, array $values): bool
    {
        $n = strlen($types);
        if ($n === 0) {
            return $values === [];
        }
        if ($n !== count($values)) {
            return false;
        }

        $args = [$types];
        foreach (array_values($values) as $i => $v) {
            $name = '_mysqli_bp_' . $i;
            ${$name} = $v;
            $args[] = &${$name};
        }

        return call_user_func_array([$stmt, 'bind_param'], $args);
    }
}

if (!function_exists('mysqli_stmt_fetch_assoc_compat')) {
    /**
     * @return array<string,mixed>|null
     */
    function mysqli_stmt_fetch_assoc_compat(mysqli_stmt $stmt): ?array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = @$stmt->get_result();
            if ($res instanceof mysqli_result) {
                $row = $res->fetch_assoc();
                $res->free();

                return $row ?: null;
            }
            // mysqlnd missing: get_result() missing or returns false — use bind_result below
        }

        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            return null;
        }

        $meta = $stmt->result_metadata();
        if (!$meta) {
            return null;
        }

        $row = [];
        $refs = [];
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $refs[] = &$row[$field->name];
        }
        $meta->free();

        if ($refs === [] || !@call_user_func_array([$stmt, 'bind_result'], $refs)) {
            return null;
        }

        if (!$stmt->fetch()) {
            return null;
        }

        $out = [];
        foreach ($row as $k => $v) {
            $out[$k] = $v;
        }

        return $out;
    }
}

if (!function_exists('mysqli_stmt_fetch_all_assoc_compat')) {
    /**
     * @return list<array<string,mixed>>
     */
    function mysqli_stmt_fetch_all_assoc_compat(mysqli_stmt $stmt): array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = @$stmt->get_result();
            if ($res instanceof mysqli_result) {
                $rows = [];
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();

                return $rows;
            }
        }

        $stmt->store_result();
        $meta = $stmt->result_metadata();
        if (!$meta) {
            return [];
        }

        $row = [];
        $refs = [];
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $refs[] = &$row[$field->name];
        }
        $meta->free();

        if ($refs === [] || !@call_user_func_array([$stmt, 'bind_result'], $refs)) {
            return [];
        }

        $rows = [];
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($row as $k => $v) {
                $copy[$k] = $v;
            }
            $rows[] = $copy;
        }

        return $rows;
    }
}
