/**
 * Creates a debounced function that delays invoking `func` until after `wait`
 * milliseconds have elapsed since the last time the debounced function was invoked.
 * 
 * @param {Function} func - The function to debounce
 * @param {number} [wait=0] - The number of milliseconds to delay
 * @param {Object} [options={}] - The options object
 * @param {boolean} [options.leading=false] - Invoke on the leading edge of the timeout
 * @param {boolean} [options.trailing=true] - Invoke on the trailing edge of the timeout
 * @param {number} [options.maxWait] - Maximum time func is allowed to be delayed before it's invoked
 * @returns {Function} The debounced function with cancel, flush, and pending methods
 */
export function debounce(func, wait = 0, options = {}) {
    if (typeof func !== 'function') {
        throw new TypeError('Expected a function');
    }

    const leading = options.leading === true;
    const trailing = options.trailing !== false;
    const maxWait = options.maxWait !== undefined ? Math.max(Number(options.maxWait) || 0, wait) : undefined;
    const hasMaxWait = maxWait !== undefined;

    let timerId;
    let maxTimerId;
    let lastArgs;
    let lastThis;
    let result;
    let lastCallTime;
    let lastInvokeTime = 0;

    function invokeFunc(time) {
        const args = lastArgs;
        const thisArg = lastThis;

        lastArgs = lastThis = undefined;
        lastInvokeTime = time;
        
        result = func.apply(thisArg, args);
        return result;
    }

    function shouldInvoke(time) {
        const timeSinceLastCall = time - lastCallTime;
        const timeSinceLastInvoke = time - lastInvokeTime;

        return (
            lastCallTime === undefined ||
            timeSinceLastCall >= wait ||
            timeSinceLastCall < 0 ||
            (hasMaxWait && timeSinceLastInvoke >= maxWait)
        );
    }

    function remainingWait(time) {
        const timeSinceLastCall = time - lastCallTime;
        const timeSinceLastInvoke = time - lastInvokeTime;
        
        const timeWaiting = wait - timeSinceLastCall;

        return hasMaxWait
            ? Math.min(timeWaiting, maxWait - timeSinceLastInvoke)
            : timeWaiting;
    }

    function trailingEdge(time) {
        timerId = undefined;

        if (trailing && lastArgs) {
            return invokeFunc(time);
        }
        
        lastArgs = lastThis = undefined;
        return result;
    }

    function timerExpired() {
        const time = Date.now();
        
        if (shouldInvoke(time)) {
            return trailingEdge(time);
        }
        
        timerId = setTimeout(timerExpired, remainingWait(time));
    }

    function leadingEdge(time) {
        lastInvokeTime = time;
        
        timerId = setTimeout(timerExpired, wait);
        
        return leading ? invokeFunc(time) : result;
    }

    function cancel() {
        if (timerId !== undefined) {
            clearTimeout(timerId);
        }
        if (maxTimerId !== undefined) {
            clearTimeout(maxTimerId);
        }
        
        lastInvokeTime = 0;
        lastArgs = lastThis = timerId = lastCallTime = maxTimerId = undefined;
    }

    function flush() {
        if (timerId === undefined) {
            return result;
        }
        
        return trailingEdge(Date.now());
    }

    function pending() {
        return timerId !== undefined;
    }

    function debounced(...args) {
        const time = Date.now();
        const isInvoking = shouldInvoke(time);

        lastArgs = args;
        lastThis = this;
        lastCallTime = time;

        if (isInvoking) {
            if (timerId === undefined) {
                return leadingEdge(time);
            }
            
            if (hasMaxWait) {
                clearTimeout(timerId);
                timerId = setTimeout(timerExpired, wait);
                
                return invokeFunc(time);
            }
        }

        if (timerId === undefined) {
            timerId = setTimeout(timerExpired, wait);
        }

        return result;
    }

    debounced.cancel = cancel;
    debounced.flush = flush;
    debounced.pending = pending;

    return debounced;
}

/**
 * Creates a throttled function that only invokes `func` at most once per
 * every `wait` milliseconds.
 * 
 * @param {Function} func - The function to throttle
 * @param {number} [wait=0] - The number of milliseconds to throttle invocations to
 * @param {Object} [options={}] - The options object
 * @param {boolean} [options.leading=true] - Invoke on the leading edge of the timeout
 * @param {boolean} [options.trailing=true] - Invoke on the trailing edge of the timeout
 * @returns {Function} The throttled function with cancel, flush, and pending methods
 */
export function throttle(func, wait = 0, options = {}) {
    if (typeof func !== 'function') {
        throw new TypeError('Expected a function');
    }

    const leading = options.leading !== false;
    const trailing = options.trailing !== false;

    return debounce(func, wait, {
        leading: leading,
        maxWait: wait,
        trailing: trailing
    });
}

/**
 * Returns the index of the first element in the array that satisfies the
 * provided testing function. Otherwise, it returns -1.
 * 
 * @param {Array|ArrayLike} array - The array to search
 * @param {Function|Object|string|Array} [predicate] - The predicate to match
 * @param {number} [fromIndex=0] - The index to start searching from
 * @returns {number} The index of the found element, or -1
 */
export function findIndex(array, predicate, fromIndex) {
    const length = array == null ? 0 : array.length;
    if (!length) {
        return -1;
    }

    let index = fromIndex == null ? 0 : toInteger(fromIndex);
    
    if (index < 0) {
        index = Math.max(length + index, 0);
    }

    const iteratee = getIteratee(predicate);

    for (let i = index; i < length; i++) {
        if (iteratee(array[i], i, array)) {
            return i;
        }
    }

    return -1;
}

/**
 * Returns the index of the last element in the array that satisfies the
 * provided testing function. Otherwise, it returns -1.
 * 
 * @param {Array|ArrayLike} array - The array to search
 * @param {Function|Object|string|Array} [predicate] - The predicate to match
 * @param {number} [fromIndex=array.length-1] - The index to start searching from
 * @returns {number} The index of the found element, or -1
 */
export function findLastIndex(array, predicate, fromIndex) {
    const length = array == null ? 0 : array.length;
    if (!length) {
        return -1;
    }

    let index = fromIndex == null ? length - 1 : toInteger(fromIndex);
    
    if (index < 0) {
        index = Math.max(length + index, 0);
    } else if (index >= length) {
        index = length - 1;
    }

    const iteratee = getIteratee(predicate);

    for (let i = index; i >= 0; i--) {
        if (iteratee(array[i], i, array)) {
            return i;
        }
    }

    return -1;
}

/**
 * Converts a value to an integer.
 * 
 * @param {*} value - The value to convert
 * @returns {number} The converted integer
 * @private
 */
function toInteger(value) {
    const result = Number(value);
    
    if (Number.isNaN(result)) {
        return 0;
    }
    
    if (result === 0 || !Number.isFinite(result)) {
        return result === 0 ? 0 : (result > 0 ? Number.MAX_SAFE_INTEGER : -Number.MAX_SAFE_INTEGER);
    }
    
    return Math.trunc(result);
}

/**
 * Creates an iteratee function based on the type of the predicate.
 * 
 * @param {Function|Object|string|Array} [predicate] - The predicate to convert
 * @returns {Function} The iteratee function
 * @private
 */
function getIteratee(predicate) {
    if (predicate == null) {
        return (value) => value;
    }

    if (typeof predicate === 'function') {
        return predicate;
    }

    if (typeof predicate === 'string') {
        return (obj) => obj != null && Boolean(obj[predicate]);
    }

    if (Array.isArray(predicate)) {
        const [key, value] = predicate;
        return (obj) => obj != null && obj[key] === value;
    }

    if (typeof predicate === 'object') {
        const keys = Object.keys(predicate);
        return (obj) => {
            if (obj == null) {
                return false;
            }
            return keys.every((key) => obj[key] === predicate[key]);
        };
    }

    return (value) => value;
}