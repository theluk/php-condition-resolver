# php-condition-resolver
A small helper for defining conditions and matching them against some data

# Use
A condition resolver, that can uses some data array and a condition array
to determine, if the data array matches the conditions.

a typical single condition looks like

    array(
        "cmp" => "==",
        "left" => "value1",
        "right" => "value2"
    )

as "left" and "right" keys you can use a path syntax, which will try to fetch
the data from the main data object.

    "left" => "My.Object.some_value"
    "right" => "something"

$this->setConditions() method expects an array of many conditions arrays

    array(
        array( "cmp" => ... ),
        array( "cmp" => ... )
    )

you can also use two operators "or" and "and".

    array(
        array("or" => array(
            array( "cmp" => ... ),
            array( "cmp" => ... )
        ))
    ) 

the operators can also be nested, the only important thing is that an
operator syntax expects that the array must contain a single key, which is
"or" or "and" and the value is again a list of conditions.

another example that should work

    array(
        "or" => array(
            array(
                "and" => array(
                    array( condition )
                    array( condition )
                    array(
                        "or" => array(
                         array ( ... )
                        )
                    )
                )
            )
        )
    )

# Tests
run

    phpunit ./src/ConditionResolverTest.php
