<?php

include_once "prelude.php";

use PHPUnit\Framework\TestCase;

final class TrimHtmlTest extends TestCase {
	/** @test */
	public function emptyParagraph() : void
	{
		$this->assertSame('', trimHtml('<p></p>'));
	}

	/** @test */
	public function paragraphWithSpace() : void
	{
		$this->assertSame('', trimHtml('<p> </p>'));
	}

	/** @test */
	public function paragraphWithNbsp() : void
	{
		$this->assertSame('', trimHtml('<p>&nbsp;</p>'));
	}

	/** @test */
	public function paragraphWithBr() : void
	{
		$this->assertSame('', trimHtml('<p><br></p>'));
	}

	/** @test */
	public function paragraphWithSelfClosingBr() : void
	{
		$this->assertSame('', trimHtml('<p><br /></p>'));
	}

	/** @test */
	public function paragraphWithEmptySpan() : void
	{
		$this->assertSame('', trimHtml('<p><span></span></p>'));
	}

	/** @test */
	public function paragraphWithSpanContainingNbsp() : void
	{
		$this->assertSame('', trimHtml('<p><span>&nbsp;</span></p>'));
	}

	/** @test */
	public function multipleEmptyParagraphsAroundContent() : void
	{
		$this->assertSame('<p>Hello</p>', trimHtml('<p><br></p><p><br></p><p>Hello</p><p><br></p>'));
	}

	/** @test */
	public function nbspParagraphsAroundContent() : void
	{
		$this->assertSame('<p>Content here</p>', trimHtml('<p>&nbsp;</p><p>Content here</p><p>&nbsp;</p>'));
	}

	/** @test */
	public function emptyDivsAroundContent() : void
	{
		$this->assertSame('<p>Text</p>', trimHtml('<div></div><p>Text</p><div>&nbsp;</div>'));
	}

	/** @test */
	public function middleEmptyParagraphPreserved() : void
	{
		$this->assertSame('<p>A</p><p></p><p>B</p>', trimHtml('<p>A</p><p></p><p>B</p>'));
	}

	/** @test */
	public function imgPreserved() : void
	{
		$this->assertSame('<p><img src="x.png"></p>', trimHtml('<p><img src="x.png"></p>'));
	}

	/** @test */
	public function iframePreserved() : void
	{
		$this->assertSame('<p><iframe src="x"></iframe></p>', trimHtml('<p><iframe src="x"></iframe></p>'));
	}

	/** @test */
	public function topLevelBrStripped() : void
	{
		$this->assertSame('<p>Content</p>', trimHtml('<br><br><p>Content</p><br>'));
	}

	/** @test */
	public function nestedEmptyFormattingStripped() : void
	{
		$this->assertSame('<p>Hi</p>', trimHtml('<div><span><em></em></span></div><p>Hi</p>'));
	}

	/** @test */
	public function cleanContentUnchanged() : void
	{
		$this->assertSame('<p>Hello world</p>', trimHtml('<p>Hello world</p>'));
	}

	/** @test */
	public function trailingBrInsideContentParagraph() : void
	{
		$this->assertSame('<p>sadasd</p>', trimHtml('<p></p><p>sadasd<br><br><br></p>'));
	}

	/** @test */
	public function leadingBrInsideContentParagraph() : void
	{
		$this->assertSame('<p>text</p>', trimHtml('<p><br><br>text</p>'));
	}

	/** @test */
	public function degenerateNestedBrAndEmptyBlocks() : void
	{
		// Rennorb's test case. HTML5 parser auto-closes <p> when it encounters inner <p>,
		// producing flat siblings: <p><br></p><p></p><br>asdsad<br><p></p><br><p></p>
		// After trim: all edge empties removed, leaving bare text.
		$this->assertSame('asdsad', trimHtml('<p><br><p></p><br>asdsad<br><p></p><br></p>'));
	}

	/** @test */
	public function emptyString() : void
	{
		$this->assertSame('', trimHtml(''));
	}

	/** @test */
	public function nullByteOnlyParagraph() : void
	{
		$this->assertSame('', trimHtml('<p>' . chr(0) . '</p>'));
	}

	/** @test */
	public function tabsAndNewlinesOnly() : void
	{
		$this->assertSame('', trimHtml("<p>\t\n\r</p>"));
	}

	/** @test */
	public function mixedControlCharsAndNbsp() : void
	{
		$this->assertSame('', trimHtml('<p>' . chr(0x0B) . '&nbsp;' . chr(0x1F) . '</p>'));
	}

	/** @test */
	public function utf8ContentPreserved() : void
	{
		$this->assertSame('<p>Héllo wörld 日本語</p>', trimHtml('<p>Héllo wörld 日本語</p>'));
	}

	/** @test */
	public function emojiPreserved() : void
	{
		$this->assertSame('<p>🎮 Gaming mod</p>', trimHtml('<p>🎮 Gaming mod</p>'));
	}

	/** @test */
	public function zeroWidthSpaceIsEmpty() : void
	{
		$this->assertSame('', trimHtml('<p>' . "\xE2\x80\x8B" . '</p>'));
	}
}
