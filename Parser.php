<?php

declare(strict_types=1);

namespace Qubus\View;

use Qubus\View\Expression\AddExpression;
use Qubus\View\Expression\AndExpression;
use Qubus\View\Expression\ArrayExpression;
use Qubus\View\Expression\AttributeExpression;
use Qubus\View\Expression\CompareExpression;
use Qubus\View\Expression\ConcatExpression;
use Qubus\View\Expression\ConditionalExpression;
use Qubus\View\Expression\ConstantExpression;
use Qubus\View\Expression\DivExpression;
use Qubus\View\Expression\FilterExpression;
use Qubus\View\Expression\FunctionCallExpression;
use Qubus\View\Expression\InclusionExpression;
use Qubus\View\Expression\JoinExpression;
use Qubus\View\Expression\ModExpression;
use Qubus\View\Expression\MulExpression;
use Qubus\View\Expression\NameExpression;
use Qubus\View\Expression\NegExpression;
use Qubus\View\Expression\NotExpression;
use Qubus\View\Expression\OrExpression;
use Qubus\View\Expression\PosExpression;
use Qubus\View\Expression\StringExpression;
use Qubus\View\Expression\SubExpression;
use Qubus\View\Expression\XorExpression;
use Qubus\View\Node\AssignNode;
use Qubus\View\Node\BlockDisplayNode;
use Qubus\View\Node\BlockNode;
use Qubus\View\Node\BreakNode;
use Qubus\View\Node\CallNode;
use Qubus\View\Node\ContinueNode;
use Qubus\View\Node\ExtendsNode;
use Qubus\View\Node\ForNode;
use Qubus\View\Node\IfNode;
use Qubus\View\Node\ImportNode;
use Qubus\View\Node\IncludeNode;
use Qubus\View\Node\MacroNode;
use Qubus\View\Node\OutputNode;
use Qubus\View\Node\ParentNode;
use Qubus\View\Node\RawNode;
use Qubus\View\Node\TextNode;
use Qubus\View\Node\YieldNode;

use function array_keys;
use function array_pop;
use function call_user_func;
use function count;
use function floatval;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_callable;
use function preg_match;
use function sprintf;
use function str_replace;
use function strval;

final class Parser
{
    private TokenStream $stream;
    private $extends;
    private array $blocks;
    private array $currentBlock;
    private array $tags;
    private int $inForLoop;
    private array $macros;
    private bool $inMacro;
    private array $imports;

    public function __construct(TokenStream $stream)
    {
        $this->stream  = $stream;
        $this->extends = null;
        $this->blocks  = [];

        $this->currentBlock = [];

        $this->tags = [
            'if'       => 'parseIf',
            'for'      => 'parseFor',
            'break'    => 'parseBreak',
            'continue' => 'parseContinue',
            'extends'  => 'parseExtends',
            'assign'   => 'parseAssign',
            'block'    => 'parseBlock',
            'parent'   => 'parseParent',
            'macro'    => 'parseMacro',
            'call'     => 'parseCall',
            'yield'    => 'parseYield',
            'import'   => 'parseImport',
            'include'  => 'parseInclude',
        ];

        $this->inForLoop  = 0;
        $this->macros     = [];
        $this->inMacro    = false;
        $this->imports    = [];
    }

    /**
     * @throws SyntaxErrorException
     */
    public function parse($path, $class): Module
    {
        $body = $this->subparse();
        return new Module(
            $path,
            $class,
            $this->extends,
            $this->imports,
            $this->blocks,
            $this->macros,
            $body
        );
    }

    /**
     * @throws SyntaxErrorException
     */
    private function subparse($test = null): NodeList
    {
        $line = $this->stream->currentToken->line;
        $nodes = [];
        while (! $this->stream->isEOS()) {
            switch ($this->stream->currentToken->type) {
                case Token::TEXT:
                    $token = $this->stream->next();
                    $nodes[] = new TextNode($token->value, $token->line);
                    break;
                case Token::BLOCK_BEGIN:
                    $this->stream->next();
                    $token = $this->stream->currentToken;
                    if ($token->type !== Token::NAME) {
                        throw new SyntaxErrorException(
                            sprintf(
                                'unexpected "%s", expecting a valid tag',
                                str_replace("\n", '\n', $token->value)
                            ),
                            $token
                        );
                    }
                    if (null !== $test && $token->test($test)) {
                        return new NodeList($nodes, $line);
                    }

                    if (! in_array($token->value, array_keys($this->tags))) {
                        if (is_array($test)) {
                            $expecting = '"' . implode('" or "', $test) . '"';
                        } elseif ($test) {
                            $expecting = '"' . $test . '"';
                        } else {
                            $expecting = 'a valid tag';
                        }
                        throw new SyntaxErrorException(
                            sprintf(
                                'unexpected "%s", expecting %s',
                                str_replace("\n", '\n', $token->value),
                                $expecting
                            ),
                            $token
                        );
                    }
                    $this->stream->next();
                    if (
                        isset($this->tags[$token->value]) &&
                        is_callable([$this, $this->tags[$token->value]])
                    ) {
                        $node = call_user_func(
                            [$this, $this->tags[$token->value]],
                            $token
                        );
                    } else {
                        throw new SyntaxErrorException(
                            sprintf(
                                'missing construct handler "%s"',
                                $token->value
                            ),
                            $token
                        );
                    }
                    if (null !== $node) {
                        $nodes[] = $node;
                    }
                    break;

                case Token::OUTPUT_BEGIN:
                    $token = $this->stream->next();
                    $expr = $this->parseExpression();
                    $nodes[] = $this->parseIfModifier(
                        $token,
                        new OutputNode($expr, $token->line)
                    );
                    $this->stream->expect(Token::OUTPUT_END);
                    break;

                case Token::RAW_BEGIN:
                    $token = $this->stream->next();
                    $expr = $this->parseExpression();
                    $nodes[] = $this->parseIfModifier(
                        $token,
                        new RawNode($expr, $token->line)
                    );
                    $this->stream->expect(Token::RAW_END);
                    break;

                default:
                    throw new SyntaxErrorException(
                        'parser ended up in unsupported state',
                        $this->stream->currentToken
                    );
            }
        }
        return new NodeList($nodes, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseIf($token): BaseNode
    {
        $line = $token->line;
        $expr = $this->parseExpression();
        $this->stream->expect(Token::BLOCK_END);
        $body = $this->subparse(['elseif', 'else', 'endif']);
        $tests = [[$expr, $body]];
        $else = null;

        $end = false;
        while (! $end) {
            switch ($this->stream->next()->value) {
                case 'elseif':
                    $expr = $this->parseExpression();
                    $this->stream->expect(Token::BLOCK_END);
                    $body = $this->subparse(['elseif', 'else', 'endif']);
                    $tests[] = [$expr, $body];
                    break;
                case 'else':
                    $this->stream->expect(Token::BLOCK_END);
                    $else = $this->subparse(['endif']);
                    break;
                case 'endif':
                    $this->stream->expect(Token::BLOCK_END);
                    $end = true;
                    break;
                default:
                    throw new SyntaxErrorException('malformed if statement', $token);
                break;
            }
        }
        return new IfNode($tests, $else, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseIfModifier($token, $node): BaseNode
    {
        static $modifiers = ['if', 'unless'];

        if ($this->stream->test($modifiers)) {
            $statement = $this->stream->expect($modifiers)->value;
            $testExpr = $this->parseExpression();
            if ($statement === 'if') {
                $node = new IfNode(
                    [[$testExpr, $node]],
                    null,
                    $token->line
                );
            } elseif ($statement === 'unless') {
                $node = new IfNode(
                    [
                        [
                            new NotExpression($testExpr, $token->line),
                            $node,
                        ],
                    ],
                    null,
                    $token->line
                );
            }
        }
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseFor($token): BaseNode
    {
        $this->inForLoop++;
        $line = $token->line;
        $key = null;
        $value = $this->stream->expect(Token::NAME)->value;
        if ($this->stream->consume(Token::OPERATOR, ',')) {
            $key = $value;
            $value = $this->stream->expect(Token::NAME)->value;
        }
        $this->stream->expect(Token::OPERATOR, 'in');
        $seq = $this->parseExpression();
        $this->stream->expect(Token::BLOCK_END);
        $body = $this->subparse(['else', 'endfor']);
        $this->inForLoop--;
        if ($this->stream->currentToken->value === 'else') {
            $this->stream->next();
            $this->stream->expect(Token::BLOCK_END);
            $else = $this->subparse('endfor');
            if ($this->stream->currentToken->value !== 'endfor') {
                throw new SyntaxErrorException('malformed for statement', $token);
            }
        } elseif ($this->stream->currentToken->value === 'endfor') {
            $else = null;
        } else {
            throw new SyntaxErrorException('malformed for statement', $token);
        }
        $this->stream->next();
        $this->stream->expect(Token::BLOCK_END);
        return new ForNode($seq, $key, $value, $body, $else, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseBreak($token): BaseNode
    {
        if (! $this->inForLoop) {
            throw new SyntaxErrorException('unexpected break, not in for loop', $token);
        }
        $node = $this->parseIfModifier(
            $token,
            new BreakNode($token->line)
        );
        $this->stream->expect(Token::BLOCK_END);
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseContinue($token): BaseNode
    {
        if (! $this->inForLoop) {
            throw new SyntaxErrorException(
                'unexpected continue, not in for loop',
                $token
            );
        }
        $node = $this->parseIfModifier(
            $token,
            new ContinueNode($token->line)
        );
        $this->stream->expect(Token::BLOCK_END);
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseExtends($token)
    {
        if (null !== $this->extends) {
            throw new SyntaxErrorException('multiple extends tags', $token);
        }

        if (! empty($this->currentBlock)) {
            throw new SyntaxErrorException(
                'cannot declare extends inside blocks',
                $token
            );
        }

        if ($this->inMacro) {
            throw new SyntaxErrorException(
                'cannot declare extends inside macros',
                $token
            );
        }

        $parent = $this->parseExpression();
        $params = null;

        if ($this->stream->consume(Token::NAME, 'with')) {
            $this->stream->expect(Token::OPERATOR, '[');
            $params = $this->parseArrayExpression();
            $this->stream->expect(Token::OPERATOR, ']');
        }

        $this->extends = $this->parseIfModifier(
            $token,
            new ExtendsNode($parent, $params, $token->line)
        );

        $this->stream->expect(Token::BLOCK_END);
        return null;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseAssign($token): BaseNode
    {
        $attrs = [];
        $name = $this->stream->expect(Token::NAME)->value;
        while (
            ! $this->stream->test(Token::OPERATOR, '=') &&
            ! $this->stream->test(Token::BLOCK_END)
        ) {
            if ($this->stream->consume(Token::OPERATOR, '.')) {
                $attrs[] = $this->stream->expect(Token::NAME)->value;
            } else {
                $this->stream->expect(Token::OPERATOR, '[');
                $attrs[] = $this->parseExpression();
                $this->stream->expect(Token::OPERATOR, ']');
            }
        }
        if ($this->stream->consume(Token::OPERATOR, '=')) {
            $value = $this->parseExpression();
            $node = $this->parseIfModifier(
                $token,
                new AssignNode($name, $attrs, $value, $token->line)
            );
            $this->stream->expect(Token::BLOCK_END);
        } else {
            $this->stream->expect(Token::BLOCK_END);
            $body = $this->subparse('endassign');
            if ($this->stream->next()->value !== 'endassign') {
                throw new SyntaxErrorException('malformed set statement', $token);
            }
            $this->stream->expect(Token::BLOCK_END);
            $node = new AssignNode($name, $attrs, $body, $token->line);
        }
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseBlock($token): BaseNode
    {
        if ($this->inMacro) {
            throw new SyntaxErrorException(
                'cannot declare blocks inside macros',
                $token
            );
        }
        $name = $this->stream->expect(Token::NAME)->value;
        if (isset($this->blocks[$name])) {
            throw new SyntaxErrorException(
                sprintf('block "%s" already defined', $name),
                $token
            );
        }
        $this->currentBlock[] = $name;

        if ($this->stream->consume(Token::BLOCK_END)) {
            $body = $this->subparse('endblock');
            if ($this->stream->next()->value !== 'endblock') {
                throw new SyntaxErrorException('malformed block statement', $token);
            }
            $this->stream->consume(Token::NAME, $name);
        } else {
            $expr = $this->parseExpression();
            $body = new OutputNode($expr, $token->line);
        }
        $this->stream->expect(Token::BLOCK_END);
        array_pop($this->currentBlock);
        $this->blocks[$name] = new BlockNode($name, $body, $token->line);
        return new BlockDisplayNode($name, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseParent($token): BaseNode
    {
        if ($this->inMacro) {
            throw new SyntaxErrorException(
                'cannot call parent block inside macros',
                $token
            );
        }

        if (empty($this->currentBlock)) {
            throw new SyntaxErrorException('parent must be inside a block', $token);
        }

        $node = $this->parseIfModifier(
            $token,
            new ParentNode(
                $this->currentBlock[count($this->currentBlock) - 1],
                $token->line
            )
        );
        $this->stream->expect(Token::BLOCK_END);
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseMacro($token): void
    {
        if (! empty($this->currentBlock)) {
            throw new SyntaxErrorException(
                'cannot declare macros inside blocks',
                $token
            );
        }

        if ($this->inMacro) {
            throw new SyntaxErrorException(
                'cannot declare macros inside another macro',
                $token
            );
        }

        $this->inMacro = true;
        $name = $this->stream->expect(Token::NAME)->value;
        if (isset($this->macros[$name])) {
            throw new SyntaxErrorException(
                sprintf('macro "%s" already defined', $name),
                $token
            );
        }
        $args = [];
        if ($this->stream->consume(Token::OPERATOR, '(')) {
            while (! $this->stream->test(Token::OPERATOR, ')')) {
                if (! empty($args)) {
                    $this->stream->expect(Token::OPERATOR, ',');
                    if ($this->stream->test(Token::OPERATOR, ')')) {
                        break;
                    }
                }
                $key = $this->stream->expect(Token::NAME)->value;
                if ($this->stream->consume(Token::OPERATOR, '=')) {
                    $val = $this->parseLiteralExpression();
                } else {
                    $val = new ConstantExpression(null, $token->line);
                }
                $args[$key] = $val;
            }
            $this->stream->expect(Token::OPERATOR, ')');
        }
        $this->stream->expect(Token::BLOCK_END);
        $body = $this->subparse('endmacro');
        if ($this->stream->next()->value !== 'endmacro') {
            throw new SyntaxErrorException('malformed macro statement', $token);
        }
        $this->stream->consume(Token::NAME, $name);
        $this->stream->expect(Token::BLOCK_END);
        $this->macros[$name] = new MacroNode(
            $name,
            $args,
            $body,
            $token->line
        );
        $this->inMacro = false;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseCall($token): BaseNode
    {
        $module = null;
        $name = $this->stream->expect(Token::NAME)->value;
        if ($this->stream->consume(Token::OPERATOR, '.')) {
            $module = $name;
            $name = $this->stream->expect(Token::NAME)->value;
        }

        $args = [];

        if ($this->stream->consume(Token::OPERATOR, '(')) {
            while (! $this->stream->test(Token::OPERATOR, ')')) {
                if (! empty($args)) {
                    $this->stream->expect(Token::OPERATOR, ',');
                    if ($this->stream->test(Token::OPERATOR, ')')) {
                        break;
                    }
                }
                if (
                    $this->stream->test(Token::NAME) &&
                    $this->stream->look()->test(Token::OPERATOR, '=')
                ) {
                    $key = $this->stream->expect(Token::NAME)->value;
                    $this->stream->expect(Token::OPERATOR, '=');
                    $val = $this->parseExpression();
                    $args[$key] = $val;
                } else {
                    $args[] = $this->parseExpression();
                }
            }
            $this->stream->expect(Token::OPERATOR, ')');
        }

        $body = null;

        if ($this->stream->consume(Token::NAME, 'with')) {
            $this->stream->expect(Token::BLOCK_END);
            $body = $this->subparse('endcall');
            if ($this->stream->next()->value !== 'endcall') {
                throw new SyntaxErrorException('malformed call statement', $token);
            }
        }

        $this->stream->expect(Token::BLOCK_END);
        return new CallNode($module, $name, $args, $body, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseYield($token): BaseNode
    {
        $args = [];

        if ($this->stream->consume(Token::OPERATOR, '(')) {
            while (! $this->stream->test(Token::OPERATOR, ')')) {
                if (! empty($args)) {
                    $this->stream->expect(Token::OPERATOR, ',');
                    if ($this->stream->test(Token::OPERATOR, ')')) {
                        break;
                    }
                }
                $key = $this->stream->expect(Token::NAME)->value;
                $this->stream->expect(Token::OPERATOR, '=');
                $val = $this->parseExpression();
                $args[$key] = $val;
            }
            $this->stream->expect(Token::OPERATOR, ')');
        }

        $this->stream->expect(Token::BLOCK_END);

        return new YieldNode($args, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseImport($token): void
    {
        $import = $this->parseExpression();
        $this->stream->expect(Token::NAME, 'as');
        $module = $this->stream->expect(Token::NAME)->value;
        $this->stream->expect(Token::BLOCK_END);
        $this->imports[$module] = new ImportNode(
            $module,
            $import,
            $token->line
        );
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseInclude($token): BaseNode
    {
        $include = $this->parseExpression();
        $params = null;

        if ($this->stream->consume(Token::NAME, 'with')) {
            $this->stream->expect(Token::OPERATOR, '[');
            $params = $this->parseArrayExpression();
            $this->stream->expect(Token::OPERATOR, ']');
        }

        $node = $this->parseIfModifier(
            $token,
            new IncludeNode($include, $params, $token->line)
        );

        $this->stream->expect(Token::BLOCK_END);
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseExpression(): BaseExpression
    {
        return $this->parseConditionalExpression();
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseConditionalExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $expr1 = $this->parseXorExpression();
        while ($this->stream->consume(Token::OPERATOR, '?')) {
            $expr2 = $this->parseOrExpression();
            $this->stream->expect(Token::OPERATOR, ':');
            $expr3 = $this->parseConditionalExpression();
            $expr1 = new ConditionalExpression($expr1, $expr2, $expr3, $line);
            $line = $this->stream->currentToken->line;
        }
        return $expr1;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseXorExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseOrExpression();
        while ($this->stream->consume(Token::OPERATOR, 'xor')) {
            $right = $this->parseOrExpression();
            $left = new XorExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseOrExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseAndExpression();
        while ($this->stream->consume(Token::OPERATOR, 'or')) {
            $right = $this->parseAndExpression();
            $left = new OrExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseAndExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseNotExpression();
        while ($this->stream->consume(Token::OPERATOR, 'and')) {
            $right = $this->parseNotExpression();
            $left = new AndExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseNotExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        if ($this->stream->consume(Token::OPERATOR, 'not')) {
            $node = $this->parseNotExpression();
            return new NotExpression($node, $line);
        }
        return $this->parseInclusionExpression();
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseInclusionExpression(): BaseExpression
    {
        static $operators = ['not', 'in'];

        $line = $this->stream->currentToken->line;
        $left = $this->parseCompareExpression();
        while ($this->stream->test(Token::OPERATOR, $operators)) {
            if ($this->stream->consume(Token::OPERATOR, 'not')) {
                $this->stream->expect(Token::OPERATOR, 'in');
                $right = $this->parseCompareExpression();
                $left = new NotExpression(
                    new InclusionExpression($left, $right, $line),
                    $line
                );
            } else {
                $this->stream->expect(Token::OPERATOR, 'in');
                $right = $this->parseCompareExpression();
                $left = new InclusionExpression($left, $right, $line);
            }
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseCompareExpression(): BaseExpression
    {
        static $operators = [
            '!==',
            '===',
            '==',
            '!=',
            '<>',
            '<',
            '>',
            '>=',
            '<=',
        ];
        $line = $this->stream->currentToken->line;
        $expr = $this->parseConcatExpression();
        $ops = [];
        while ($this->stream->test(Token::OPERATOR, $operators)) {
            $ops[] = [
                    $this->stream->next()->value,
                $this->parseAddExpression(),
            ];
        }

        if (empty($ops)) {
            return $expr;
        }
        return new CompareExpression($expr, $ops, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseConcatExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseJoinExpression();
        while ($this->stream->consume(Token::OPERATOR, '~')) {
            $right = $this->parseJoinExpression();
            $left = new ConcatExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseJoinExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseAddExpression();
        while ($this->stream->consume(Token::OPERATOR, '..')) {
            $right = $this->parseAddExpression();
            $left = new JoinExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseAddExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseMulExpression();
        while ($this->stream->test(Token::OPERATOR, ['+', '-'])) {
            $operator = $this->stream->next()->value;
            $right = $this->parseMulExpression();
            $left = $operator === '+'
            ? new AddExpression($left, $right, $line)
            : new SubExpression($left, $right, $line);
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseMulExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $left = $this->parseUnaryExpression();
        while ($this->stream->test(Token::OPERATOR, ['*', '/', '%'])) {
            $operator = $this->stream->next()->value;
            $right = $this->parseUnaryExpression();
            $left = match ($operator) {
                '*' => new MulExpression($left, $right, $line),
                '/' => new DivExpression($left, $right, $line),
                '%' => new ModExpression($left, $right, $line),
            };
            $line = $this->stream->currentToken->line;
        }
        return $left;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseUnaryExpression(): BaseExpression
    {
        if ($this->stream->test(Token::OPERATOR, ['-', '+'])) {
            switch ($this->stream->currentToken->value) {
                case '-':
                    return $this->parseNegExpression();
                case '+':
                    return $this->parsePosExpression();
            }
        }
        return $this->parsePrimaryExpression();
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseNegExpression(): BaseExpression
    {
        $token = $this->stream->next();
        $node = $this->parseUnaryExpression();
        return new NegExpression($node, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parsePosExpression(): BaseExpression
    {
        $token = $this->stream->next();
        $node = $this->parseUnaryExpression();
        return new PosExpression($node, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parsePrimaryExpression(): BaseExpression
    {
        $token = $this->stream->currentToken;
        switch ($token->type) {
            case Token::CONSTANT:
            case Token::NUMBER:
            case Token::STRING:
                $node = $this->parseLiteralExpression();
                break;
            case Token::NAME:
                $this->stream->next();
                $node = new NameExpression($token->value, $token->line);
                if ($this->stream->test(Token::OPERATOR, '(')) {
                    $node = $this->parseFunctionCallExpression($node);
                }
                break;
            default:
                if ($this->stream->consume(Token::OPERATOR, '[')) {
                    $node = $this->parseArrayExpression();
                    $this->stream->expect(Token::OPERATOR, ']');
                } elseif ($this->stream->consume(Token::OPERATOR, '(')) {
                    $node = $this->parseExpression();
                    $this->stream->expect(Token::OPERATOR, ')');
                } else {
                    throw new SyntaxErrorException(
                        sprintf(
                            'unexpected "%s", expecting an expression',
                            str_replace("\n", '\n', $token->value)
                        ),
                        $token
                    );
                }
        }
        return $this->parsePostfixExpression($node);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseLiteralExpression(): BaseExpression
    {
        $token = $this->stream->currentToken;
        switch ($token->type) {
            case Token::CONSTANT:
                $this->stream->next();
                switch ($token->value) {
                    case 'true':
                        $node = new ConstantExpression(true, $token->line);
                        break;
                    case 'false':
                        $node = new ConstantExpression(false, $token->line);
                        break;
                    case 'null':
                        $node = new ConstantExpression(null, $token->line);
                        break;
                }
                break;
            case Token::NUMBER:
                $this->stream->next();
                if (preg_match('/\./', $token->value)) {
                    $node = new ConstantExpression(
                        floatval($token->value),
                        $token->line
                    );
                } else {
                    $node = new ConstantExpression(
                        intval($token->value),
                        $token->line
                    );
                }
                break;
            case Token::STRING:
                $this->stream->next();
                $node = new StringExpression(
                    strval($token->value),
                    $token->line
                );
                break;
            default:
                throw new SyntaxErrorException(
                    sprintf(
                        'unexpected "%s", expecting an expression',
                        str_replace("\n", '\n', $token->value)
                    ),
                    $token
                );
        }
        return $this->parsePostfixExpression($node);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseFunctionCallExpression($node): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $this->stream->expect(Token::OPERATOR, '(');
        $args = [];
        while (! $this->stream->test(Token::OPERATOR, ')')) {
            if (! empty($args)) {
                $this->stream->expect(Token::OPERATOR, ',');
                if ($this->stream->test(Token::OPERATOR, ')')) {
                    break;
                }
            }
            $args[] = $this->parseExpression();
        }
        $this->stream->expect(Token::OPERATOR, ')');
        return new FunctionCallExpression($node, $args, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseArrayExpression(): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $elements = [];
        do {
            $token = $this->stream->currentToken;
            if ($token->test(Token::OPERATOR, ']')) {
                break;
            }
            if (
                $token->test(Token::NAME) ||
                $token->test(Token::STRING) ||
                $token->test(Token::NUMBER)
            ) {
                if (
                    $token->test(Token::NAME) ||
                    $token->test(Token::STRING)
                ) {
                    $key = new ConstantExpression(
                        strval($token->value),
                        $line
                    );
                } else {
                    if (preg_match('/\./', $token->value)) {
                        $key = new ConstantExpression(
                            floatval($token->value),
                            $line
                        );
                    } else {
                        $key = new ConstantExpression(
                            intval($token->value),
                            $line
                        );
                    }
                }
                $this->stream->next();
                if ($this->stream->consume(Token::OPERATOR, ['=>'])) {
                    $element = $this->parseExpression();
                    $elements[] = [$key, $element];
                } else {
                    $elements[] = $key;
                }
            } else {
                $elements[] = $this->parseExpression();
            }
            $this->stream->consume(Token::OPERATOR, ',');
        } while (! $this->stream->test(Token::OPERATOR, ']'));
        return new ArrayExpression($elements, $line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parsePostfixExpression($node): BaseExpression
    {
        $stop = false;
        while (
            ! $stop &&
            $this->stream->currentToken->type === Token::OPERATOR
        ) {
            switch ($this->stream->currentToken->value) {
                case '.':
                case '[':
                    $node = $this->parseAttributeExpression($node);
                    break;
                case '|':
                    $node = $this->parseFilterExpression($node);
                    break;
                default:
                    $stop = true;
                    break;
            }
        }
        return $node;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseAttributeExpression($node): BaseExpression
    {
        $token = $this->stream->currentToken;
        if ($this->stream->consume(Token::OPERATOR, '.')) {
            $attr = new ConstantExpression(
                $this->stream->expect(Token::NAME)->value,
                $token->line
            );
        } else {
            $this->stream->expect(Token::OPERATOR, '[');
            $attr = $this->parseExpression();
            $this->stream->expect(Token::OPERATOR, ']');
        }

        $args = false;
        if ($this->stream->consume(Token::OPERATOR, '(')) {
            $args = [];
            while (! $this->stream->test(Token::OPERATOR, ')')) {
                if (count($args)) {
                    $this->stream->expect(Token::OPERATOR, ',');
                }
                $args[] = $this->parseExpression();
            }
            $this->stream->expect(Token::OPERATOR, ')');
        }
        return new AttributeExpression($node, $attr, $args, $token->line);
    }

    /**
     * @throws SyntaxErrorException
     */
    private function parseFilterExpression($node): BaseExpression
    {
        $line = $this->stream->currentToken->line;
        $filters = [];
        while ($this->stream->test(Token::OPERATOR, '|')) {
            $this->stream->next();
            $token = $this->stream->expect(Token::NAME);

            $args = [];
            if ($this->stream->test(Token::OPERATOR, '(')) {
                $this->stream->next();
                while (! $this->stream->test(Token::OPERATOR, ')')) {
                    if (! empty($args)) {
                        $this->stream->expect(Token::OPERATOR, ',');
                        if ($this->stream->test(Token::OPERATOR, ')')) {
                            break;
                        }
                    }
                    $args[] = $this->parseExpression();
                }
                $this->stream->expect(Token::OPERATOR, ')');
            }

            $filters[] = [$token->value, $args];
        }
        return new FilterExpression($node, $filters, $line);
    }
}
