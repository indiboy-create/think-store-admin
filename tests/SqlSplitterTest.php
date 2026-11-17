<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\SqlSplitter;

final class SqlSplitterTest extends TestCase
{
    public function testSplitSimpleStatements(): void
    {
        self::assertSame(
            ['SELECT 1', 'SELECT 2'],
            SqlSplitter::split('SELECT 1; SELECT 2;')
        );
    }

    public function testSplitWithoutTrailingSemicolon(): void
    {
        self::assertSame(['SELECT 1'], SqlSplitter::split('SELECT 1'));
    }

    public function testSemicolonInsideString(): void
    {
        self::assertSame(
            ["INSERT INTO t VALUES ('a;b')", 'SELECT 1'],
            SqlSplitter::split("INSERT INTO t VALUES ('a;b'); SELECT 1;")
        );
    }

    public function testSemicolonInsideBacktickIdentifier(): void
    {
        self::assertSame(
            ['SELECT `a;b` FROM t'],
            SqlSplitter::split('SELECT `a;b` FROM t;')
        );
    }

    public function testLineCommentsAreRemoved(): void
    {
        $sql = "-- first comment\nSELECT 1;\n-- second comment\nSELECT 2;";

        self::assertSame(['SELECT 1', 'SELECT 2'], SqlSplitter::split($sql));
    }

    public function testHashCommentIsRemoved(): void
    {
        self::assertSame(['SELECT 1'], SqlSplitter::split("# comment; with semicolon\nSELECT 1"));
    }

    public function testBlockCommentIsRemoved(): void
    {
        self::assertSame(['SELECT 1'], SqlSplitter::split('/* comment; */ SELECT 1'));
    }

    public function testCommentMarkerInsideStringIsKept(): void
    {
        self::assertSame(
            ["INSERT INTO t VALUES ('-- not a comment')"],
            SqlSplitter::split("INSERT INTO t VALUES ('-- not a comment');")
        );
    }

    public function testEmptySql(): void
    {
        self::assertSame([], SqlSplitter::split(''));
        self::assertSame([], SqlSplitter::split("-- only comment\n"));
    }

    public function testPackageSqlFile(): void
    {
        $file = dirname(__DIR__) . '/src/database/store_admin.sql';
        $sql = str_replace('__PREFIX__', 'think_', (string) file_get_contents($file));
        $statements = SqlSplitter::split($sql);

        self::assertCount(4, $statements);

        foreach ($statements as $statement) {
            self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS', $statement);
            self::assertStringContainsString('think_store_admin_', $statement);
        }
    }
}