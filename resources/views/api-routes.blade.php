<!DOCTYPE html>
<html>

<head>
    <title>API Routes</title>
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ccc;
        }

        th {
            background: #f5f5f5;
        }

        .yes {
            color: green;
            font-weight: bold;
        }

        .no {
            color: red;
            font-weight: bold;
        }

        .info-box {
            background: #f9f9f9;
            padding: 12px;
            border: 1px solid #ddd;
            margin-bottom: 15px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        .filters {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            background: #fafafa;
        }

        .filters select {
            padding: 6px;
            margin-right: 10px;
        }

        .url {
            color: purple;
        }

        button {
            padding: 7px;
        }

        a {
            padding: 7px;
            border: 1px solid black;
            border-radius: 3px;
            margin-left: 10px;
        }
    </style>
</head>

<body>



    <div class="info-box">
        <h2>API Routes List</h2>
        <p><strong>Base URL:</strong> {{ $baseUrl }}</p>
        <p><strong>Total API Routes:</strong> {{ $totalRoutes }}</p>
    </div>

    <div class="filters">
        <form method="GET" action="{{ url('/api-routes') }}">
            <label>
            URI Search:
            <input
                type="text"
                name="search"
                placeholder="Search URI..."
                value="{{ $search }}"
                style="padding:6px;"
            >
        </label>
            <label>
                Method:
                <select name="method">
                    <option value="">All</option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                        <option value="{{ $method }}" {{ $methodFilter === $method ? 'selected' : '' }}>
                            {{ $method }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Auth Required:
                <select name="auth">
                    <option value="">All</option>
                    <option value="yes" {{ $authFilter === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no" {{ $authFilter === 'no' ? 'selected' : '' }}>No</option>
                </select>
            </label>

            <button type="submit">Apply</button>
            <a href="{{ url('/api-routes') }}">Reset</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>URI</th>
                <th>Auth Required</th>
            </tr>
        </thead>
        <tbody>
            @forelse($routes as $route)
                <tr>
                    <td>{{ $route['method'] }}</td>
                    <td><strong class="url">{{ $baseUrl }}</strong>{{ $route['uri'] }}</td>
                    <td class="{{ $route['auth_required'] ? 'yes' : 'no' }}">
                        {{ $route['auth_required'] ? 'YES' : 'NO' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;">No routes found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
