# YCSBClientCloudSpanner

Implements [YCSB](https://github.com/brianfrankcooper/YCSB) clients for cloud
Spanner in various languages.

## Client Spec

### Input

Client must parse flags in YCSB format:

```
<command> -P <path/to/workload_file> [-p key=value]*
```

Where `command` can be either `load` or `run`. The path to the workload file
specifies a [YCSB workload file](https://github.com/brianfrankcooper/YCSB/blob/master/workloads/workload_template).
Client must read the following parameters from the workload file.
1. `readproportion`
2. `updateproportion`
3. `scanproportion`
4. `insertproportion`

Client must understand the following flags:
1. `table`: The table to use in the benchmark.
1. `cloudspanner.instance`: The cloud Spanner instance to use.
1. `cloudspanner.database`: The cloud Spanner database to use.
1. `client_type`: The client type, can be `java`, `python`. It's recommended
   that the client do a sanity check on the client type.
1. `num_worker`: Number of workers. This can mean different things in
   different languages. For example, in Go, it's number of goroutines (threads),
   while in node.js, it's number of EventHandlers (all sharing a single thread).
1. `recordcount`: Number of records.
1. `operationcount`: Number of operations.

### Output

The client must print out to `STDOUT` the output in the YCSB output format.

```
[TYPE], <description>, <metric>
```

Where `TYPE` can be `OVERALL`, `INSERT`, `UPDATE`, `READ`, or `SCAN`, and
must start with `OVERALL`, `description` can be either a description or the
bucket of latency in millisecond. See the example below.

```
[OVERALL], RunTime(ms), 172520.0
[OVERALL], Throughput(ops/sec), 289.8214699744957
[INSERT], Operations, 50000
[INSERT], AverageLatency(us), 67995.35378
[INSERT], LatencyVariance(us), 1.2098756170999897E10
[INSERT], MinLatency(us), 26700
[INSERT], MaxLatency(us), 5103261
[INSERT], 95thPercentileLatency(us), 143000
[INSERT], 99thPercentileLatency(us), 253000
[INSERT], Return=OK, 50000
[INSERT], 0, 0
[INSERT], 1, 0
[INSERT], 2, 0
[INSERT], 3, 0
[INSERT], 4, 0
[INSERT], 5, 0
[INSERT], 6, 0
[INSERT], 7, 0
[INSERT], 8, 0
[INSERT], 9, 0
...
[INSERT], 999, 0
[INSERT], >1000, 27
```

## Binary Spec

To integrate with [PerfKitBenchmarks](https://github.com/GoogleCloudPlatform/PerfKitBenchmarker),
a `.tar.gz` package should be built for each client. The recommended name is
`ycsb-<language>.<version>.tar.gz`. Once untarred, there must be an
executable at relative path `bin/ycsb`. It is recommended to include all
dependencies in the `.tar.gz` package. If there is any unincluded dependencies,
PerfKitBenchmarker needs to install it on the VMs.

### Java

We use [YCSB](https://github.com/brianfrankcooper/YCSB), which is in Java, for
the Java client.

### Python

Sample command to make the Python package.

```
$ tar -cvzf ycsb-python.0.0.5.tar.gz py/*
```
