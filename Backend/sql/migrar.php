<?php
// ============================================================
//  KOSMOS — Executor de migrações (só linha de comando)
//  Arquivo: Backend/sql/migrar.php
//
//  Guarda na tabela `migracoes` quais arquivos desta pasta já
//  rodaram e aplica só os que faltam, em ordem de nome (a data
//  no começo do nome é a ordem). Acaba com o "na minha máquina
//  funciona" de alguém que esqueceu uma migração.
//
//  Uso (da raiz do AppPrincipal):
//      php Backend/sql/migrar.php                 aplica as pendentes
//      php Backend/sql/migrar.php --status        só lista
//      php Backend/sql/migrar.php --marcar-todas  registra todas como
//                                                 aplicadas SEM rodar
//
//  Primeira vez num banco que já existe (importado do kosmos.sql, ou
//  com as migrações rodadas na mão): use --marcar-todas. Reaplicar
//  tudo não é seguro — a 2026-09-25_progresso.sql, por exemplo,
//  credita XP retroativo.
//
//  No XAMPP: C:\xampp\php\php.exe Backend/sql/migrar.php
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/conexao.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$opcao = $argv[1] ?? '';
if (!in_array($opcao, ['', '--status', '--marcar-todas'], true)) {
    fwrite(STDERR, "Opção desconhecida: $opcao\nUse: --status | --marcar-todas | (nada)\n");
    exit(1);
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset(DB_CHARSET);

    $db->query('CREATE TABLE IF NOT EXISTS `migracoes` (
                    `arquivo`    varchar(190) NOT NULL,
                    `aplicada_em` datetime NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`arquivo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $aplicadas = array_column($db->query('SELECT arquivo FROM migracoes')->fetch_all(MYSQLI_ASSOC), 'arquivo');

    $arquivos = glob(__DIR__ . '/*.sql');
    sort($arquivos, SORT_STRING);
    $pendentes = array_values(array_filter(
        array_map('basename', $arquivos),
        fn ($a) => !in_array($a, $aplicadas, true)
    ));

    if ($opcao === '--status') {
        foreach (array_map('basename', $arquivos) as $a) {
            echo (in_array($a, $aplicadas, true) ? '  [ok]       ' : '  [pendente] ') . $a . "\n";
        }
        exit(0);
    }

    if ($opcao === '--marcar-todas') {
        $ins = $db->prepare('INSERT IGNORE INTO migracoes (arquivo) VALUES (?)');
        foreach ($pendentes as $a) {
            $ins->bind_param('s', $a);
            $ins->execute();
            echo "  marcada: $a\n";
        }
        echo count($pendentes) . " migração(ões) marcada(s) como aplicada(s).\n";
        exit(0);
    }

    // Registro vazio + banco com tabelas = banco antigo, sem histórico.
    // Não adivinha: pede para a pessoa marcar a base primeiro.
    if (!$aplicadas && $db->query("SHOW TABLES LIKE 'usuarios'")->num_rows > 0) {
        fwrite(STDERR,
            "Este banco já tem tabelas, mas nenhuma migração registrada.\n" .
            "Se ele está em dia (veio do kosmos.sql ou você rodou tudo na mão):\n" .
            "    php Backend/sql/migrar.php --marcar-todas\n" .
            "Se falta alguma, rode-a com o cliente mysql antes de marcar.\n");
        exit(1);
    }

    if (!$pendentes) {
        echo "Nada a fazer: o banco está em dia.\n";
        exit(0);
    }

    $ins = $db->prepare('INSERT INTO migracoes (arquivo) VALUES (?)');
    foreach ($pendentes as $a) {
        echo "  aplicando $a ... ";
        $db->multi_query((string) file_get_contents(__DIR__ . '/' . $a));
        // Consome todos os resultados: com o report STRICT, um erro em
        // qualquer comando do arquivo vira exceção aqui e para tudo
        do {
            if ($r = $db->store_result()) {
                $r->free();
            }
        } while ($db->more_results() && $db->next_result());

        $ins->bind_param('s', $a);
        $ins->execute();
        echo "ok\n";
    }
    echo count($pendentes) . " migração(ões) aplicada(s).\n";
} catch (mysqli_sql_exception $e) {
    // O MySQL não desfaz CREATE/ALTER: os comandos do arquivo que vieram
    // antes do erro já valeram. Ele não é registrado, então roda de novo
    // na próxima vez — por isso as migrações usam IF NOT EXISTS.
    fwrite(STDERR, "\nERRO: " . $e->getMessage() . "\n" .
        "Este arquivo NÃO foi registrado (os comandos antes do erro já rodaram)\n" .
        "e nenhum dos seguintes foi aplicado. Corrija e rode de novo.\n");
    exit(1);
}
