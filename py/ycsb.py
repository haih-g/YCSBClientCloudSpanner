"""The YCSB cient in Python.

Usage:

  $ testing/performance/cloud_benchmarks/scripts/cloud_spanner_client.sh \
    --ycsb_record_count=50000 --ycsb_operation_count=200000 --client_type=java \
    --num_worker=32 --noskip_spanner_setup --skip_spanner_teardown
  $ export GOOGLE_APPLICATION_CREDENTIALS=/usr/local/google/home/haih/cloud-storage-benchmarks.json
  $ export GCLOUD_PROJECT=cloud-storage-benchmarks
  $ python py/ycsb.py run -P pkb/workloada -p table=usertable \
    -p cloudspanner.instance=ycsb-542756a4 -p recordcount=5000 \
    -p operationcount=100 -p cloudspanner.database=ycsb

"""

import optparse
import random
import string
import timeit

from google.cloud import spanner


# TODO: Generate keys following the YCSB key distribution with given record
# count.
KEYS = ['user7592730522480249475',
        'user7592843607087762572',
        'user7592948837698091358',
        'user7592956691695275669',
        'user7593061922305604455',
        'user7593290482992389923',
        'user7593403567599903020',
        'user7593508798210231806',
        'user7593516652207416117',
        'user7593621882817744903',
        'user7593629736814929214',
        'user7593734967425258000',
        'user7593848052032771097',
        'user7594181843329885351',
        'user7594294927937398448',
        'user759475617603741620',
        'user7595188679746653150',
        'user7595301764354166247',
        'user7596409868082749357',
        'user7596522952690262454',
        'user759696324293342777',
        'user7596969828594889805',
        'user7597416704499517156',
        'user7597529789107030253',
        'user7597863580404144507',
        'user7597976665011657604',
        'user7598081895621986390',
        'user7598089749619170701',
        'user759809408900855874',
        'user7598194980229499487',
        'user7598202834226683798',
        'user7598308064837012584',
        'user7598421149444525681',
        'user7598649710131311149',
        'user7598762794738824246',
        'user7598868025349153032',
        'user7598875879346337343',
        'user7598981109956666129',
        'user7598983501428425403',
        'user7599094194564179226',
        'user7599096586035938500',
        'user7599201816646267286',
        'user7599209670643451597',
        'user7599314901253780383',
        'user7599427985861293480',
        'user7599654155076319674',
        'user7599874861765920831',
        'user7599987946373433928',
        'user7600101030980947025',
        'user7600214115588460122']


DUMMY_OUTPUT = """[OVERALL], RunTime(ms), 172520.0
[OVERALL], Throughput(ops/sec), 289.8214699744957
[INSERT], Operations, 50000
[INSERT], AverageLatency(us), 67995.35378
[INSERT], LatencyVariance(us), 1.2098756170999897E10
[INSERT], MinLatency(us), 26700
[INSERT], MaxLatency(us), 5103261
[INSERT], 95thPercentileLatency(us), 143000
[INSERT], 99thPercentileLatency(us), 253000
[INSERT], Return=OK, 50000
[INSERT], 0, 0"""


OPERATIONS = ['readproportion', 'updateproportion', 'scanproportion',
              'insertproportion']


def ParseOptions():
  """Parses options."""
  parser = optparse.OptionParser()
  parser.add_option('-P', '--workload', action='store', dest='workload',
                    default='', help='The path to a YCSB workload file.')
  parser.add_option('-p', '--parameter', action='append', dest='parameters',
                    default=[], help='The key=value pair of parameter.')

  options, args = parser.parse_args()

  parameters = {}
  parameters['command'] = args[0]

  for parameter in options.parameters:
    parts = parameter.strip().split('=')
    parameters[parts[0]] = parts[1]

  with open(options.workload, 'r') as f:
    for line in f.readlines():
      parts = line.split('=')
      key = parts[0].strip()
      if key in OPERATIONS:
        parameters[key] = parts[1].strip()

  return parameters


def OpenDatabase(parameters):
  """Opens a database specified by the parameters from ParseOptions()."""
  spanner_client = spanner.Client()
  instance_id = parameters['cloudspanner.instance']
  instance = spanner_client.instance(instance_id)
  database_id = parameters['cloudspanner.database']
  database = instance.database(database_id)

  return database


def Read(database, table, key):
  """Does a single read operation."""
  result = database.execute_sql('SELECT u.* FROM usertable u WHERE u.key="%s"' %
                                key)


def Update(database, table, key):
  """Does a single update operation."""
  field = random.randrange(10)
  value = ''.join(random.choice(string.printable) for i in range(100))
  with database.batch() as batch:
    batch.update(table=table, columns=('id', 'field%d' % field),
                 values=[(key, value)])


def Insert(database, table, key):
  """Does a single insert operation."""
  raise Exception('Insert is not implemented.')


def Scan(database, table, key):
  """Does a single scan operation."""
  raise Exception('Scan is not implemented.')


def DoOperation(database, table, operation, latencies_ms):
  """Does a single operation and records latency."""
  key = random.choice(KEYS)
  start = timeit.default_timer()
  if operation == 'read':
    Read(database, table, key)
  elif operation == 'update':
    Update(database, table,  key)
  elif operation == 'insert':
    Insert(database, table, key)
  elif operation == 'scan':
    Scan(database, table, key)
  else:
    raise Exception('Unknown operation: %s' % operation)
  end = timeit.default_timer()
  latencies_ms[operation].append((end - start) * 1000)


def AggregateMetrics(latencies_ms):
  """Aggregates metrics."""
  # TODO: Print aggregated metrics following YCSB output format.
  print latencies_ms


def RunWorkload(database, parameters):
  """Runs workload against the database."""
  # TODO: Run workload in multiple threads, use parameters['num_worker'].
  total_weight = 0.0
  weights = []
  operations = []
  latencies_ms = {}
  for operation in OPERATIONS:
    weight = float(parameters[operation])
    if weight <= 0.0:
      continue
    total_weight += weight
    op_code = operation.split('proportion')[0]
    operations.append(op_code)
    weights.append(total_weight)
    latencies_ms[op_code] = []

  i = 0
  operation_count = int(parameters['operationcount'])
  while i < operation_count:
    i += 1
    weight = random.uniform(0, total_weight)
    for j in range(len(weights)):
      if weight <= weights[j]:
        DoOperation(database, parameters['table'], operations[j], latencies_ms)
        break

  AggregateMetrics(latencies_ms)


if __name__ == '__main__':
  print DUMMY_OUTPUT

  parameters = ParseOptions()
  if parameters['command'] == 'run':
    database = OpenDatabase(parameters)
    RunWorkload(database, parameters)
  else:
    raise Exception('Command %s not implemented.' % parameters['command'])
