<?php

namespace Tests\Feature\ChoiceValidation;

use App\Enums\ChoiceValidationReason;
use App\Services\ChoiceValidation\ChoiceColumnResolver;
use App\Services\ChoiceValidation\ChoiceRowRuleValidator;
use RuntimeException;
use Tests\TestCase;

final class ChoiceValidationFoundationContractTest extends TestCase
{
    public function test_choice_headers_are_config_driven(): void
    {
        config(['choice-validation.maximum_allowed_choices'=>3]);
        $resolver=app(ChoiceColumnResolver::class);
        self::assertSame(['user','reg','opt_01','opt_02','opt_03'],$resolver->expectedHeaders());
        self::assertSame($resolver->expectedHeaders(),$resolver->validateHeaders(['user','reg','opt_01','opt_02','opt_03']));
    }

    public function test_header_beyond_maximum_is_blocking_with_locked_reason_code(): void
    {
        config(['choice-validation.maximum_allowed_choices'=>2]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(ChoiceValidationReason::ChoiceExceedsMaximumAllowedLimit->value);
        app(ChoiceColumnResolver::class)->validateHeaders(['user','reg','opt_01','opt_02','opt_03']);
    }

    public function test_at_least_one_choice_is_required(): void
    {
        config(['choice-validation.maximum_allowed_choices'=>3]);
        $result=app(ChoiceRowRuleValidator::class)->validate(['opt_01'=>null,'opt_02'=>'','opt_03'=>null]);
        self::assertSame(0,$result['choice_count']);
        self::assertStringContainsString(ChoiceValidationReason::NoChoiceProvided->value,implode(' ', $result['errors']));
    }

    public function test_choice_gap_is_invalid_and_source_position_is_preserved(): void
    {
        config(['choice-validation.maximum_allowed_choices'=>3]);
        $result=app(ChoiceRowRuleValidator::class)->validate(['opt_01'=>'110','opt_02'=>null,'opt_03'=>'117']);
        self::assertSame(2,$result['choice_count']);
        self::assertStringContainsString(ChoiceValidationReason::ChoiceSequenceGap->value,implode(' ', $result['errors']));
        self::assertSame('117',$result['choices'][2]['code']);
        self::assertSame(3,$result['choices'][2]['position']);
    }
}
