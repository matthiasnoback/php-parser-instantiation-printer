This project provides a so-called [InstantiationPrinter](src/InstantiationPrinter.php). I'm not the only one who has attempted to build such a thing: <https://github.com/nikic/PHP-Parser/issues/566>

Example: `$instance = new self();`
In PHP-Parser nodes this will be:

```
array(
    0: Stmt_Expression(
        expr: Expr_Assign(
            var: Expr_Variable(
                name: instance
            )
            expr: Expr_New(
                class: Name(
                    parts: array(
                        0: self
                    )
                )
                args: array(
                )
            )
        )
    )
)
```

When printed by the `InstantiationPrinter` this becomes:

```php
new PhpParser\Node\Stmt\Expression(
    new PhpParser\Node\Expr\Assign(
        new PhpParser\Node\Expr\Variable('instance'),
        new PhpParser\Node\Expr\New_(
            new PhpParser\Node\Name('self')
        )
    )
);
```
