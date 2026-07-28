<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use MageObsidian\ModernFrontend\Service\Cms\ClassCandidates;
use PHPUnit\Framework\TestCase;

/**
 * The same extraction runs over the build's snapshot and over the content as it
 * is now, and the difference decides what gets compiled on the fly. So what
 * matters is that it is deterministic and that it reads what an author wrote —
 * not prose that happens to look like a utility.
 */
class ClassCandidatesTest extends TestCase
{
    public function testReadsTheClassesOfEveryElement(): void
    {
        $html = '<div class="grid md:grid-cols-3 gap-6"><p class="text-sm">Hi</p></div>';

        $this->assertSame(
            ['gap-6', 'grid', 'md:grid-cols-3', 'text-sm'],
            ClassCandidates::extract($html)
        );
    }

    public function testIgnoresProseThatIsNotInAClassAttribute(): void
    {
        $html = '<p>Our grid of products is text-sm to read.</p><span class="italic">x</span>';

        $this->assertSame(['italic'], ClassCandidates::extract($html));
    }

    public function testReadsBothQuoteStyles(): void
    {
        $this->assertSame(['a', 'b'], ClassCandidates::extract("<i class='a'></i><i class=\"b\"></i>"));
    }

    public function testReadsAClassListAWidgetStoredEscaped(): void
    {
        $html = '{{widget type="Vendor\\Module" html="<div class=&quot;p-4 rounded-lg&quot;></div>"}}';

        $this->assertSame(['p-4', 'rounded-lg'], ClassCandidates::extract($html));
    }

    public function testIsStableRegardlessOfOrderAndRepetition(): void
    {
        $a = ClassCandidates::extract('<i class="z-10 flex"></i><i class="flex"></i>');
        $b = ClassCandidates::extract('<i class="flex"></i><i class="flex z-10"></i>');

        $this->assertSame($a, $b);
        $this->assertSame(['flex', 'z-10'], $a);
    }

    public function testHandlesContentWithNoMarkupAtAll(): void
    {
        $this->assertSame([], ClassCandidates::extract(''));
        $this->assertSame([], ClassCandidates::extract('Just a sentence.'));
    }

    public function testMergeIsSortedAndDeduplicated(): void
    {
        $this->assertSame(
            ['a', 'b', 'c'],
            ClassCandidates::merge(['b', 'a'], ['c', 'a'], [])
        );
        $this->assertSame([], ClassCandidates::merge([], []));
    }
}
