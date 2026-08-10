<?php

namespace App\Http\Requests;

final class UpdateCircularEntryRequest extends StoreCircularEntryRequest
{
    // Store and update intentionally share the same mandatory audited-change contract.
}
