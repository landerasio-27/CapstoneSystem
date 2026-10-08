{{--
    Renders whichever of the three official documents this report is. The three
    keep their own templates — a PAR is not an ICS and neither is a RIS — and
    this is only the lookup between the report and its paper.
--}}
@include("admin.reports.documents._{$type}")
