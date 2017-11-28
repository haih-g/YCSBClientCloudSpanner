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

Client must understand the following flags specified using ```[-p key=value]```:
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
1. `cloudspanner.channels`: Number of cloud Spanner channles to use.

Client must be albe ignore unknown arguments or flags. The real command can be:

```
<binary> run cloudspanner -P /opt/pkb/workloada \
  -p cloudspanner.host=https://spanner.googleapis.com/ \
  -p cloudspanner.instance=ycsb-bb9e6936 \
  -p cloudspanner.project=cloud-spanner-client-benchmark \
  -p client_type=dotnet -p threads=32 -p zeropadding=12 \
  -p num_worker=32 -p target=None -p measurementtype=histogram \
  -p table=usertable -p operationcount=200000 -p recordcount=50000 \
  -p cloudspanner.database=ycsb -p maxexecutiontime=1800
```

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
[INSERT], 99.9thPercentileLatency(us), 660000
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

### Node.js

Sample command to make Node package.

```sh
$ tar -cvzf ycsb-node.0.0.5.tar.gz node/*
```

### Python

Sample command to make the Python package.

```
$ tar -cvzf ycsb-python.0.0.5.tar.gz py/*
```

## To Run

First you need to setup a Cloud Spanner instance and database. Then you can use
[YCSB](https://github.com/brianfrankcooper/YCSB) to load the database. Then you
can run the client benchmarks.

### Set up the database

```
$ gcloud spanner instances create ycsb-instance --nodes 1 \
  --config regional-us-central1 --description YCSB
$ gcloud spanner databases create ycsb --instance ycsb-instance
$ gcloud spanner databases ddl update ycsb --instance ycsb-instance \
  --ddl="CREATE TABLE usertable (
           id     STRING(MAX),
           field0 STRING(MAX),
           field1 STRING(MAX),
           field2 STRING(MAX),
           field3 STRING(MAX),
           field4 STRING(MAX),
           field5 STRING(MAX),
           field6 STRING(MAX),
           field7 STRING(MAX),
           field8 STRING(MAX),
           field9 STRING(MAX),
         ) PRIMARY KEY(id)"
```

### Use YCSB to load data

You need to set up some environment variables first. You should use your own
gcloud credentials and project.

```
  $ export GOOGLE_APPLICATION_CREDENTIALS=/usr/local/google/home/haih/cloud-spanner-client-benchmark.json
  $ export GCLOUD_PROJECT=cloud-spanner-client-benchmark
```

Then download YCSB and load the database.

```
$ curl https://storage.googleapis.com/cloud-spanner-ycsb-custom-release/ycsb-cloudspanner-binding-0.13.0.tar.gz | tar -xzv
$ ycsb-cloudspanner-binding-0.13.0/bin/ycsb load cloudspanner \
  -P ycsb-cloudspanner-binding-0.13.0/workloads/workloada \
  -p table=usertable -p cloudspanner.instance=ycsb-instance \
  -p recordcount=5000 -p operationcount=100 -p cloudspanner.database=ycsb \
  -threads 32
```

### Run client benchmarks

Use Python as an example.

```
$ python py/ycsb.py run -P pkb/workloada -p table=usertable \
  -p cloudspanner.instance=ycsb-542756a4 -p recordcount=5000 \
  -p operationcount=100 -p cloudspanner.database=ycsb -p num_worker=1
```
