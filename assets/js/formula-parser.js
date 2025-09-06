// @ts-nocheck
/*!
 * Lightweight Formula Parser (UMD) for MNS Navasan Plus
 * Supports: numbers, + - * / ^ %, parentheses, unary minus,
 * variables (x, myVar, [myVar]), basic functions: sqrt, abs, floor, ceil, round, min, max.
 * API compatible (enough) with hot-formula-parser style:
 *   const parser = new FormulaParser.Parser();
 *   const out = parser.parse('2^x + max(3, y)', { x: 4, y: 5 }); // {error:null, result: 21}
 * Also exposes parser.evaluate(expr, vars) → number
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.FormulaParser = factory();
    root.formulaParser = root.FormulaParser; // compat with old examples
  }
}(this, function () {
  'use strict';

  function isDigit(ch){ return ch >= '0' && ch <= '9'; }
  function isAlpha(ch){ return /[A-Za-z_]/.test(ch); }
  function isAlnum(ch){ return /[A-Za-z0-9_]/.test(ch); }
  function isSpace(ch){ return ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r'; }

  // Operators table
  var OPS = {
    '+': {p:1, a:'L', f:function(a,b){return a+b;}},
    '-': {p:1, a:'L', f:function(a,b){return a-b;}},
    '*': {p:2, a:'L', f:function(a,b){return a*b;}},
    '/': {p:2, a:'L', f:function(a,b){return a/b;}},
    '%': {p:2, a:'L', f:function(a,b){return a%b;}},
    '^': {p:3, a:'R', f:function(a,b){return Math.pow(a,b);}}
  };

  // Allowed functions
  var FUN = {
    sqrt:  function(x){ return Math.sqrt(x); },
    abs:   function(x){ return Math.abs(x); },
    floor: function(x){ return Math.floor(x); },
    ceil:  function(x){ return Math.ceil(x); },
    round: function(x){ return Math.round(x); },
    min:   function(){ return Math.min.apply(null, arguments); },
    max:   function(){ return Math.max.apply(null, arguments); }
  };

  // Tokenizer → returns array of tokens
  function tokenize(input){
    var s = (input || '').toString();
    var i = 0, L = s.length, tks = [];
    function peek(){ return s[i]; }
    function next(){ return s[i++]; }
    function skipWS(){ while(i<L && isSpace(s[i])) i++; }

    while(i < L){
      skipWS();
      if (i>=L) break;
      var ch = peek();

      // number: 123 or 123.45 or .5
      if (isDigit(ch) || (ch === '.' && i+1 < L && isDigit(s[i+1]))){
        var start = i; i++;
        while(i<L && (isDigit(s[i]) || s[i] === '.')) i++;
        tks.push({type:'num', val: parseFloat(s.slice(start, i))});
        continue;
      }

      // variable or function name (letters, digits, underscore), possibly bracketed: [myVar]
      if (isAlpha(ch) || ch === '['){
        if (ch === '['){
          // bracketed name until ]
          i++; var startB = i;
          while(i<L && s[i] !== ']') i++;
          var nameB = s.slice(startB, i);
          if (s[i] === ']') i++;
          // function if immediately followed by '('
          skipWS();
          if (s[i] === '('){
            tks.push({type:'fn',  val:nameB});
          } else {
            tks.push({type:'var', val:nameB});
          }
          continue;
        } else {
          var startW = i; i++;
          while(i<L && isAlnum(s[i])) i++;
          var word = s.slice(startW, i);
          // detect function (next non-space is '(')
          var j = i; while(j<L && isSpace(s[j])) j++;
          if (s[j] === '('){
            tks.push({type:'fn', val: word});
          } else {
            tks.push({type:'var', val: word});
          }
          continue;
        }
      }

      // operators and punctuation
      if (ch === '+' || ch === '-' || ch === '*' || ch === '/' || ch === '^' || ch === '%' ){
        tks.push({type:'op', val: ch}); i++; continue;
      }
      if (ch === '(' || ch === ')' || ch === ',' ){
        tks.push({type: ch}); i++; continue;
      }

      // string literal "..." or '...'
      if (ch === '"' || ch === "'"){
        var q = ch; i++; var startQ = i;
        while(i<L && s[i] !== q) i++;
        var str = s.slice(startQ, i);
        if (s[i] === q) i++;
        tks.push({type:'str', val: str});
        continue;
      }

      // unknown char
      throw new Error("Unexpected character '"+ch+"' at "+i);
    }

    // mark unary minus (−x) as 'u-'
    for (var k=0; k<tks.length; k++){
      var tk = tks[k];
      if (tk.type === 'op' && tk.val === '-'){
        var prev = tks[k-1];
        if (!prev || (prev.type === 'op' || prev.type === '(' || prev.type === ',')){
          // FIX: keep type as 'op' so later stages handle it
          tks[k] = {type:'op', val:'u-'};
        }
      }
    }
    return tks;
  }

  // Shunting-yard to RPN (Reverse Polish Notation)
  function toRPN(tokens){
    var out = [], stack = [];
    // To support functions with N args, we maintain arg count on '(' frames
    var argCount = [];

    for (var i=0; i<tokens.length; i++){
      var tk = tokens[i];

      if (tk.type === 'num' || tk.type === 'str' || tk.type === 'var'){
        out.push(tk);
        continue;
      }

      if (tk.type === 'fn'){
        stack.push(tk); // function token
        continue;
      }

      if (tk.type === ','){
        // pop until '('
        while (stack.length && stack[stack.length-1].type !== '('){
          out.push(stack.pop());
        }
        // bump current '(' arg counter
        if (!argCount.length) throw new Error('Misplaced comma');
        argCount[argCount.length-1]++;
        continue;
      }

      if (tk.type === 'op'){
        var o1 = tk.val;
        // unary minus has highest precedence
        var p1 = (tk.val === 'u-') ? 4 : OPS[o1].p;
        var a1 = (tk.val === 'u-') ? 'R' : OPS[o1].a;
        while (stack.length){
          var top = stack[stack.length-1];
          if (top.type === 'op'){
            var o2 = top.val;
            var p2 = (o2 === 'u-') ? 4 : OPS[o2].p;
            if ((a1 === 'L' && p1 <= p2) || (a1 === 'R' && p1 < p2)){
              out.push(stack.pop());
              continue;
            }
          }
          break;
        }
        stack.push(tk);
        continue;
      }

      if (tk.type === '('){
        stack.push(tk);
        argCount.push(1); // at least one arg if we meet a value before ')'
        continue;
      }

      if (tk.type === ')'){
        while (stack.length && stack[stack.length-1].type !== '('){
          out.push(stack.pop());
        }
        if (!stack.length) throw new Error('Mismatched parentheses');
        stack.pop(); // pop '('
        var ac = argCount.pop();

        // if function on top, move it to output (attach arg count)
        if (stack.length && stack[stack.length-1].type === 'fn'){
          var fn = stack.pop();
          fn.args = ac || 0;
          out.push(fn);
        }
        continue;
      }
    }

    while (stack.length){
      var t = stack.pop();
      if (t.type === '(') throw new Error('Mismatched parentheses');
      out.push(t);
    }
    return out;
  }

  // Evaluate RPN with given variables map
  function evalRPN(rpn, vars){
    var st = [];
    function readVar(name){
      if (vars && Object.prototype.hasOwnProperty.call(vars, name)){
        var v = vars[name];
        if (typeof v === 'number') return v;
        var n = Number(v);
        if (!isNaN(n)) return n;
      }
      // unknown variable → NAME error
      throw new Error('#NAME?');
    }

    for (var i=0; i<rpn.length; i++){
      var tk = rpn[i];

      if (tk.type === 'num'){
        st.push(tk.val); continue;
      }
      if (tk.type === 'str'){
        var num = Number(tk.val);
        st.push(isNaN(num) ? 0 : num);
        continue;
      }
      if (tk.type === 'var'){
        st.push(readVar(tk.val)); continue;
      }
      if (tk.type === 'op'){
        if (tk.val === 'u-'){
          var a = st.pop(); st.push(-a); continue;
        }
        var b = st.pop(), a2 = st.pop();
        // FIX: handle division OR modulo by zero
        if ((tk.val === '/' || tk.val === '%') && b === 0) throw new Error('#DIV/0!');
        st.push( OPS[tk.val].f(a2, b) );
        continue;
      }
      if (tk.type === 'fn'){
        var argc = tk.args || 0, args = [];
        for (var k=0; k<argc; k++) args.unshift(st.pop());
        var fn = FUN[tk.val];
        if (!fn) throw new Error('#NAME?');
        st.push(fn.apply(null, args));
        continue;
      }
    }
    if (!st.length) return 0;
    return st[0];
  }

  function Parser(){
    this.error  = null;
    this.result = 0;

    /**
     * Parse & evaluate expression.
     * @param {string} expr
     * @param {Object} vars (optional) named variables: { x:2, y:3, myVar:5 }
     * @returns {{error: (string|null), result: (number|null)}}
     */
    this.parse = function(expr, vars){
      try{
        var tokens = tokenize(expr || '');
        var rpn    = toRPN(tokens);
        var out    = evalRPN(rpn, vars || {});
        this.error  = null;
        this.result = out;
        return { error: null, result: out };
      }catch(err){
        var code = (''+err.message);
        // normalize common errors
        if (code.indexOf('#DIV/0!') === 0) {
          this.error = '#DIV/0!';
        } else if (code.indexOf('#NAME?') === 0){
          this.error = '#NAME?';
        } else {
          this.error = '#ERROR!';
        }
        this.result = null;
        return { error: this.error, result: null };
      }
    };

    /**
     * Direct evaluate helper (returns number or NaN on error)
     */
    this.evaluate = function(expr, vars){
      var o = this.parse(expr, vars);
      return o.error ? NaN : o.result;
    };
  }

  return { Parser: Parser };
}));