<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial; }
        h2 { color: #333; }
    </style>
</head>
<body>

<h1>{{ $cv['name'] }}</h1>
<p>{{ $cv['email'] }}</p>

<h2>Skills</h2>
<ul>
    @foreach($cv['skills'] as $skill)
        <li>{{ $skill }}</li>
    @endforeach
</ul>

<h2>Experience</h2>
@foreach($cv['experiences'] as $exp)
    <p><strong>{{ $exp['job_title'] }}</strong> - {{ $exp['company_name'] }}</p>
@endforeach

<h2>Education</h2>
@foreach($cv['educations'] as $edu)
    <p>{{ $edu['degree'] }} - {{ $edu['institution'] }}</p>
@endforeach

</body>
</html>
