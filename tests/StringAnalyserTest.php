<?php

namespace Fazed\Strowel\Test;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Fazed\Strowel\Contracts\StringAnalyserContract;

final class StringAnalyserTest extends TestCase
{
    use ArraySubsetAsserts;

    private const STRING_WITH_BLOCKS = '[SUB] some random show - 01 [1080p][1234567].mkv';
    private const STRING_WITHOUT_BLOCKS = 'some random show - 01 .mkv';
    private const STRING_WITH_UNBALANCED_BLOCKS = '[SUB) some random show - 01 [1080p][1234567].mkv';
    private const STRING_WITH_INDISTINCT_BLOCKS = '[SUB][SUB] some random show - 01 [1080p][1234567].mkv';
    private const STRING_WITH_RECURSIVE_BLOCKS = '(Recursive (test(test2)) [SUB {1}]) some random show - 01 [1080p][1234567].mkv';
    private const STRING_WITH_CLOSING_DELIMITER_ON_START = ')[some block][block] test string [test](.zip';

    /** @test */
    public function it_can_analyse_string_w_recursive_blocks(): void
    {
        $blocks = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_RECURSIVE_BLOCKS)
            ->getBlocks();

        $this->assertCount(7, $blocks);
    }

    /** @test */
    public function it_can_analyse_string_wo_blocks(): void
    {
        $blocks = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITHOUT_BLOCKS)
            ->getBlocks();

        $this->assertCount(0, $blocks);
    }

    /** @test */
    public function it_can_analyse_string_w_blocks(): void
    {
        $blocks = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_BLOCKS)
            ->getBlocks();

        $this->assertCount(3, $blocks);

        self::assertArraySubset(
            ['SUB', '1080p', '1234567'],
            $blocks
        );
    }

    /** @test */
    public function it_can_clean_source_string(): void
    {
        $cleanString = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_BLOCKS)
            ->getCleanString();

        $this->assertSame(static::STRING_WITHOUT_BLOCKS, $cleanString);
    }

    /** @test */
    public function it_can_ignore_unbalanced_blocks(): void
    {
        /** @var StringAnalyserContract $analyser */
        $analyser = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_UNBALANCED_BLOCKS);

        $this->assertCount(2, $analyser->getBlocks());

        self::assertArraySubset(['1080p', '1234567'], $analyser->getBlocks());
    }

    /** @test */
    public function it_can_filter_distinct_blocks(): void
    {
        /** @var StringAnalyserContract $analyser */
        $analyser = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_INDISTINCT_BLOCKS);

        self::assertArraySubset(
            ['SUB', '1080p', '1234567'],
            $analyser->getBlocks()
        );

        $this->assertSame(static::STRING_WITHOUT_BLOCKS, $analyser->getCleanString());
    }

    /** @test */
    public function it_can_ignore_closing_delimiter_on_start(): void
    {
        /** @var StringAnalyserContract $analyser */
        $analyser = app(StringAnalyserContract::class)
            ->setSourceString(static::STRING_WITH_CLOSING_DELIMITER_ON_START);

        self::assertArraySubset(
            ['some block', 'block', 'test'],
            $analyser->getBlocks()
        );

        $this->assertSame(') test string', $analyser->getCleanString());
    }
}
