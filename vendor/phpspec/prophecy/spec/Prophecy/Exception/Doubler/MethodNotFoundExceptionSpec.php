<?php

namespace spec\Prophecy\Exception\Doubler;

use PhpSpec\ObjectBehavior;
use spec\Prophecy\Exception\Prophecy;

class MethodNotFoundExceptionSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('', 'UserController', 'getName', array(1, 2, 3));
    }

    function it_is_DoubleException()
    {
        $this->shouldHaveType('Prophecy\Exception\Doubler\DoubleException');
    }

    function it_has_MethodName()
    {
        $this->getMethodName()->shouldReturn('getName');
    }

    function it_has_classnamej()
    {
        $this->getClassname()->shouldReturn('UserController');
    }

    function it_has_an_arguments_list()
    {
        $this->getArguments()->shouldReturn(array(1, 2, 3));
    }

    function it_has_a_default_null_argument_list()
    {
        $this->beConstructedWith('', 'UserController', 'getName');
        $this->getArguments()->shouldReturn(null);
    }
}
