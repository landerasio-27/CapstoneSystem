{{-- Renders a single document's form layout: the type-specific form, as the office
     files the printed paper. Expects: $document. Shared by pdf.blade.php and
     pdf_bundle.blade.php.

     The form carries its own number in its own field ("PAR No:", "ICS No:"), so
     no coding strip is printed over it: the downloaded paper is the paper. --}}
@switch($document->type)
    @case('IAR') @include('admin.documents.forms.iar') @break
    @case('PAR') @include('admin.documents.forms.par') @break
    @case('ICS') @include('admin.documents.forms.ics') @break
    @case('RIS') @include('admin.documents.forms.ris') @break
    @default @include('admin.documents.forms.par')
@endswitch
