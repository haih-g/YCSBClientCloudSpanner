'use strict';

const crypto = require('crypto');
const PQueue = require('p-queue');
const random = require('lodash.random');
const timeSpan = require('time-span');

const OPERATIONS = [
  'readproportion',
  'updateproportion',
  'scanproportion',
  'insertproportion'
];

class Workload {
  constructor(database, options) {
    this.database = database;
    this.options = options;

    this.queue = new PQueue();
    this.weights = [];
    this.totalWeight = 0;
    this.operations = [];
    this.latencies = {};
    this.opCounts = {};
    this.totalOpCount = 0;

    for (let operation of OPERATIONS) {
      let weight = parseFloat(this.options.get(operation));

      if (weight <= 0) {
        continue;
      }

      let shortOpName = operation.replace('proportion', '');

      this.operations.push(shortOpName);
      this.latencies[shortOpName] = [];
      this.totalWeight += weight;
      this.weights.push(this.totalWeight);
    }
  }

  getRandomKey() {
    return this.keys[random(this.keys.length - 1)];
  }

  loadKeys() {
    const table = this.options.get('table');
    const query = `SELECT u.id FROM ${table} u`;

    return this.database
      .run(query)
      .then(([rows]) => rows.map(row => row[0].value))
      .then(keys => (this.keys = keys));
  }

  run() {
    const operationCount = parseInt(this.options.get('operationcount'));
    const end = timeSpan();

    for (let i = 0; i < operationCount; i++) {
      let randomWeight = Math.random() * this.totalWeight;

      this.weights.forEach((weight, j) => {
        const operation = this.operations[j];

        if (randomWeight <= weight) {
          this.queue.add(() => this.runOperation(operation));
        }
      });
    }

    return this.queue.onIdle().then(() => (this.duration = end()));
  }

  runOperation(operation) {
    if (typeof this[operation] !== 'function') {
      throw new Error(`unsupported operation: ${type}`);
    }

    const end = timeSpan();

    return this[operation]().then(() => this.latencies[operation].push(end()));
  }

  read() {
    const table = this.options.get('table');
    const id = this.getRandomKey();
    const query = `SELECT u.* FROM ${table} u WHERE u.id="${id}"`;

    return this.database.getTransaction({ readonly: true }).then(([txn]) => {
      return txn.run(query).then(() => txn.end());
    });
  }

  update() {
    const table = this.database.table(this.options.get('table'));
    const id = this.getRandomKey();
    const field = `field${random(9)}`;
    const value = crypto.randomBytes(100).toString('hex');

    return table.update({ id, [field]: value });
  }
}

module.exports = Workload;
