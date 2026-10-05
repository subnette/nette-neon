<?php declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
use Tester\Assert;
use Tracy\Dumper;


require __DIR__ . '/../bootstrap.php';


$stream = (new Neon\Lexer)->tokenize("\t# comment\r\n  name: '\u{17D}'\r\n\r\n");
Assert::same([
	[Neon\Token::Whitespace, "\t", 1, 1, 0],
	[Neon\Token::Comment, '# comment', 1, 2, 1],
	[Neon\Token::Newline, "\n", 1, 11, 10],
	[Neon\Token::Whitespace, '  ', 2, 1, 11],
	[Neon\Token::Literal, 'name', 2, 3, 13],
	[':', ':', 2, 7, 17],
	[Neon\Token::Whitespace, ' ', 2, 8, 18],
	[Neon\Token::String, "'\u{17D}'", 2, 9, 19],
	[Neon\Token::Newline, "\n\n", 2, 13, 23],
	[Neon\Token::End, '', 4, 1, 25],
], array_map(fn(Neon\Token $token) => [
	$token->type,
	$token->text,
	$token->position->line,
	$token->position->column,
	$token->position->offset,
], $stream->tokens));

// Failed matches still skip trivia, but never consume the significant token.
Assert::false($stream->is(Neon\Token::Literal));
Assert::same(2, $stream->getIndex());
Assert::null($stream->tryConsume(Neon\Token::Literal));
Assert::same(2, $stream->getIndex());
Assert::same($stream->tokens[2], $stream->tryConsume(Neon\Token::Newline));
Assert::true($stream->is(kind: Neon\Token::Literal));
Assert::same(4, $stream->getIndex());
Assert::same('  ', $stream->getIndentation());
Assert::false($stream->is((string) Neon\Token::Literal));
Assert::true($stream->is(kind: Neon\Token::String, other: Neon\Token::Literal));
Assert::same($stream->tokens[4], $stream->tryConsume(kind: Neon\Token::Literal));
Assert::same(5, $stream->getIndex());
Assert::true($stream->is(kind: ':'));
$stream->seek(9);
Assert::false($stream->is());
Assert::true($stream->is(kind: Neon\Token::End));

$stream = (new Neon\Lexer)->tokenize(" \t# comment");
Assert::null($stream->tryConsume(Neon\Token::Literal));
Assert::same(2, $stream->getIndex());
Assert::false($stream->is());
Assert::true($stream->is(Neon\Token::End));


$input = <<<'XX'

	# hello
	first: # first comment
		# another comment
		- a  # a comment
	next:
		- [k,
			l, m:
		n]
	second:
		sub:
			a: 1
			b: 2
	third:
		- entity(a: 1)
		- entity(a: 1)foo()bar
	- a: 1
	  b: 2
	- - c
	dash subblock:
	- a
	- b
	text: """
	     one
	     two
	"""
	# world

	XX;


$lexer = new Neon\Lexer;
$parser = new Neon\Parser;
$stream = $lexer->tokenize($input);
$node = $parser->parse($stream);

Assert::matchFile(
	__DIR__ . '/fixtures/Parser.nodes.neon',
	$node->toString(),
);

$traverser = new Traverser;
$traverser->traverse($node, function (Node $node) use ($stream) {
	@$node->code = ''; // dynamic property is deprecated
	foreach (array_slice($stream->tokens, $node->startTokenPos, $node->endTokenPos - $node->startTokenPos + 1) as $token) {
		$node->code .= $token->text;
	}

	unset($node->startTokenPos, $node->endTokenPos);
});

Assert::same(
	strtr(file_get_contents(__DIR__ . '/fixtures/Parser.nodes.txt'), ["\r\n" => "\n"]),
	Dumper::toText($node, [Dumper::HASH => false, Dumper::DEPTH => 20]),
);
