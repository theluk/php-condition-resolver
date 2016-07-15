<?php

namespace theluk;

include_once('ConditionResolver.php');

class ConditionResolverTest extends \PHPUnit_Framework_TestCase {

    var $CR;

    function setup() {

        $this->CR = new ConditionResolver();
        $obj = array(
            "test" => array(
                "value" => 1,
                "obj" => array( 
                    "value" => "foo"
                ),
                "obj2" => array(
                    "value" => "Foo"
                ),
                "ar" => array("one", "two", "three"),
                "url" => "www.foo.bar",
                "nullvalue" => null,

            )
        );
        $this->CR->setData($obj);

    }

    function testExtractValue() {

        $this->assertEquals("foo", $this->CR->extractValue("test.obj.value"));
        $this->assertEquals("foo", $this->CR->extractValue("foo"));

        // not found strings, should not be converted to null
        $this->assertEquals("foo.test", $this->CR->extractValue("foo.test"));
        // found values should return null, when they are null
        $this->assertNull($this->CR->extractValue("test.nullvalue"));
    }

    function testIsMatch() {

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_CONTAINS,
            "left" => "test.obj.value",
            "right" => "fo"
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_CONTAINS,
            "left" => "test.obj.value",
            "right" => "test.obj2.value"
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_CONTAINS,
            "left" => "test.obj.value",
            "right" => "haBer"
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_IN,
            "right" => "test.ar",
            "left" => "three"
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_NULL,
            "left" => "test.obj.value"
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_NNULL,
            "left" => "test.obj.value"
        )));

    }

    function testResolve() {

        $conditionOr = array(
            array(
                ConditionResolver::COND_OP_OR => array(
                    array(
                        "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                        "left" => "test.obj.value",
                        "right" => "haber"
                    ),
                    array(
                        "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                        "left" => "test.obj.value",
                        "right" => "test.obj2.value"
                    )
                )
            )
        );

        $this->CR->setConditions($conditionOr);
        $this->assertTrue($this->CR->resolve());

        $conditionDirectOr = array(
            ConditionResolver::COND_OP_OR => array(
                array(
                    "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                    "left" => "test.obj.value",
                    "right" => "haber"
                ),
                array(
                    "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                    "left" => "test.obj.value",
                    "right" => "test.obj2.value"
                )
            )
        );

        $this->CR->setConditions($conditionOr);
        $this->assertTrue($this->CR->resolve());

        $conditionDirectOr = array(
            "or" => array(
                array(
                    "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                    "left" => "test.obj.value",
                    "right" => "haber"
                ),
                array(
                    "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                    "left" => "test.obj.value",
                    "right" => "test.obj2.value"
                )
            )
        );

        $this->CR->setConditions($conditionOr);
        $this->assertTrue($this->CR->resolve());

        $this->CR->setConditions(array(
            "and" => array(
                array(
                    "cmp" => "==",
                    "left" => "test",
                    "right" => "test"
                ),
                array(
                    "and" => array(
                        array(
                            "cmp" => "==",
                            "left" => "other",
                            "right" => "other"
                        )
                    )
                )
            )
        ));
        $this->assertTrue($this->CR->resolve());

    }


    function testParseCondtion() {

        $this->assertEquals(
            array(
                "cmp" => ConditionResolver::COND_CMP_CONTAINS,
                "left" => "test.obj.value",
                "right" => "haber"
            ),
            $this->CR->parseCondition("test.obj.value contains haber")
        );

        $this->CR->setConditions(array(
            "or" => array(
                "test.obj.value contains haber",
                "test.obj.value == test.obj2.value"
            )
        ));

        $this->assertTrue($this->CR->resolve());
    }

    function testCondMatch() {

        $condition = $this->CR->parseCondition("test.url match /www\..+\.bar/");
        $this->assertTrue($this->CR->isMatch($condition));

        $condition = $this->CR->parseCondition("test.url match /www\..+\.com/");
        $this->assertFalse($this->CR->isMatch($condition));
        
        

    }

    function testAll() {
        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ALL,
            "left" => array("one", "two", "three", "four"),
            "right" => array("one", "three"),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ALL,
            "left" => array("one", "two", "three", "four"),
            "right" => array("one", "three", "five"),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ALL,
            "left" => null,
            "right" => array("one", "three"),
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ALL,
            "left" => "test.ar",
            "right" => array("one", "three"),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ALL,
            "left" => "aslkdkk",
            "right" => 12314
        )));
    }

    function testAny() {
        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => array("one", "two", "three", "four"),
            "right" => array("one", "three"),
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => array("one", "two", "three", "four"),
            "right" => array("one", "three", "five"),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => array(17),
            "right" => array("one", "three", "five"),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => null,
            "right" => array("one", "three"),
        )));

        $this->assertTrue($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => "test.ar",
            "right" => array("one", "three", 23482),
        )));

        $this->assertFalse($this->CR->isMatch(array(
            "cmp" => ConditionResolver::COND_CMP_ANY,
            "left" => "aslkdkk",
            "right" => 12314
        )));
    }
}
