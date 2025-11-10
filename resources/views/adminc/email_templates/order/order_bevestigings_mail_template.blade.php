@extends('adminc.email_templates.base.layout')

@section('content')
    <p>Geachte heer {{ $persoon_achternaam }},</p>

    <p>
        Hierbij bevestig ik de afspraak voor het laten uitvoeren van medische onderzoeken
        op <strong>{{ $datum_afspraak }}</strong> om <strong>{{ $tijd_afspraak }}</strong>
        in <strong>{{ $plaats_afspraak }}</strong>.
    </p>

    <p>
        Graag ontvangen wij uiterlijk <strong>{{ $datum_bevestiging }}</strong>
        een definitieve bevestiging van u, zodat ik uw schriftelijke akkoord heb
        en de afspraak definitief kan bevestigen bij de kliniek.
    </p>

    <p>
        In de bijlagen treft u ook een onderzoekkaart aan, waarin u kunt lezen
        hoe een onderzoek als deze zal verlopen en wat u ervan mag verwachten.
        De gezondheidsvragenlijst dient u in te vullen en uiterlijk
        <strong>{{ $datum_vragenlijst }}</strong> aan ons terug te sturen.
    </p>

    <p>
        De afspraak vindt plaats bij de <strong>Ambulante Kardiologie Augusta Klinik</strong>
        aan de Bergstrasse 26, 44791 te Bochum.
    </p>

    <p>
        Wanneer u in de parkeergarage (P1 of P2 – hiervoor krijgt u een uitrijkaartje) bent aangekomen,
        dient u contact op te nemen met de begeleid(st)er. Dit mag op nummer
        <strong>{{ $telefoon_begeleider }}</strong>.
        Hij/zij zal u dan ophalen.
    </p>

    <p>
        Voor na bloed- en urineafname dient u zelf wat te eten mee te nemen.
        Wij zorgen wel voor koffie/thee.
    </p>

    <p>
        U dient nuchter te zijn vanaf <strong>{{ $tijd_nuchter }}</strong> die ochtend.
        Voor dit tijdstip adviseren wij u om wel wat te eten. In de tussentijd mag u wel water drinken,
        maar geen cafeïne houdende dranken en/of etenswaren nuttigen.
    </p>

    <p>
        Ter voorbereiding adviseren wij u een extra setje gemakkelijk zittende kleding mee te nemen,
        zodat u zich kunt omkleden voor de inspanning-ECG.
        Ook is het verzoek om een (kleine) handdoek mee te nemen, zodat u zich even kunt opfrissen
        na de fietstest (inspannings-ECG).
    </p>

    <p>
        Houd rekening met mogelijke verkeersdrukte en vertrek op tijd.
        Als u nog vragen heeft kunt u ons altijd even bellen op nummer
        <strong>{{ $telefoon_kantoor }}</strong>.
    </p>

    <p>
        Wij vernemen graag uw akkoord voor dit onderzoek en wensen u alvast
        veel succes op uw onderzoeksdag.
    </p>

    <p><br></p>
@endsection
