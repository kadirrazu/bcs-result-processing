<style>
body{font-family:DejaVu Sans,sans-serif;font-size:8px}
h2{font-size:12px;margin:0 0 4px}
.sub{font-size:9px;margin-bottom:6px}
.t{width:100%;border-collapse:collapse}
.t th,.t td{border:1px solid #555;padding:3px;vertical-align:middle}
.t th,.c{text-align:center}
.choices{font-size:7px;line-height:1.5}
.alloc{color:#d63939;font-weight:bold}
</style>

<h2>{{ $post->post_code }} — {{ $post->post_title }} - Organization: {{ $post->entity ?: '—' }} - Ministry: {{ $post->ministry ?: '—' }}</h2>
<div class="sub">
    <strong>{{ $title }}</strong> · Total Post: {{ $post->post_count }} · Allocated: {{ $post->allocated_post ?? 0 }} · Exam: {{ $examinationName }}
</div>

<table class="t">
    <thead>
        <tr>
            <th>SL</th>
            <th>Common Merit</th>
            @if($mode === 'booklet')
                <th>Candidate Information</th>
            @endif
            <th>Allocation Ready Choice</th>
            <th>Choice Position</th>
            <th>Candidate Quota</th>
            <th>Final Allocation</th>
            <th>Basis</th><th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $r)
            @php
                $choices = json_decode((string) $r->allocation_ready_choices, true) ?: [];
            @endphp
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td class="c"><b>{{ $r->common_merit_position }}</b></td>

                @if($mode === 'booklet')
                    <td>
                        <b>Reg:</b> {{ $r->reg }}<br>
                        <b>Name:</b> {{ $r->name }}<br>
                        <b>DOB:</b> {{ $r->birth_date }}
                    </td>
                @endif

                <td class="choices">
                    @foreach($choices as $ci => $choice)
                        <span class="{{ (string) $choice === (string) $r->allocated_post_code ? 'alloc' : '' }}">#{{ str_pad((string) ($ci + 1), 2, '0', STR_PAD_LEFT) }} {{ $choice }}</span>
                        @if(($ci + 1) % 10 === 0)
                            <br>
                        @else
                            &nbsp;
                        @endif
                    @endforeach
                </td>

                <td class="c">{{ $r->report_choice_position ?: '—' }}</td>
                <td class="c">{{ collect(['CFF' => $r->has_cff, 'EM' => $r->has_em, 'PHC' => $r->has_phc])->filter()->keys()->implode(', ') ?: 'Non Quota' }}</td>
                <td class="c {{ (string) $r->allocated_post_code === (string) $post->post_code ? 'alloc' : '' }}">{{ $r->allocated_post_code ?: 'Unallocated' }}</td>
                <td class="c">{{ $r->allocation_basis ?: '—' }}</td><td>{{ $r->historical_exclusion_reason ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
