<?php

namespace App\Http\Controllers;

use App\Models\Lead;

class BuyerPhotoGalleryController extends Controller
{
    /**
     * The signed URL is supplied only to the selected email recipients. It
     * expires automatically and does not expose a CRM session or deal data.
     */
    public function show(int $lead)
    {
        $lead = Lead::withoutGlobalScopes()->with(['property', 'photos'])->findOrFail($lead);

        return view('buyer-gallery.show', compact('lead'));
    }
}
